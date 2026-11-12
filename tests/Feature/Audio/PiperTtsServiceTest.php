<?php

use App\Models\AudioBriefing;
use App\Models\User;
use App\Services\Audio\PiperTtsException;
use App\Services\Audio\PiperTtsService;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Database\QueryException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Minimale gueltige WAV-Datei: 16 kHz, 16 Bit, mono, `$seconds` Sekunden Stille.
 */
function fakeWav(float $seconds = 2.0): string
{
    $data = str_repeat("\0", (int) (32000 * $seconds));

    return 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'
        .'fmt '.pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16)
        .'data'.pack('V', strlen($data)).$data;
}

beforeEach(function () {
    Storage::fake('local');
    $this->voices = sys_get_temp_dir().'/nexoma-voices-'.bin2hex(random_bytes(4));
    mkdir($this->voices);

    foreach (config('services.piper.voices') as $voice) {
        touch($this->voices.'/'.$voice.'.onnx');
    }

    config(['services.piper.data_dir' => $this->voices, 'services.piper.python_binary' => '/opt/piper/venv/bin/python3']);
});

afterEach(function () {
    array_map('unlink', glob($this->voices.'/*') ?: []);
    rmdir($this->voices);
});

/**
 * Fakt Piper: schreibt `$wav` in die per `-f` uebergebene Datei.
 */
function fakePiper(string $wav, int $exitCode = 0): void
{
    Process::fake(function (PendingProcess $process) use ($wav, $exitCode): ProcessResult {
        $command = $process->command;
        $output = $command[array_search('-f', $command, true) + 1];
        file_put_contents($output, $wav);

        return Process::result(exitCode: $exitCode);
    });
}

test('the config maps all six languages to the real voice files', function () {
    expect(config('services.piper.voices'))->toBe([
        'de' => 'de_DE-thorsten-high',
        'en' => 'en_US-lessac-high',
        'es' => 'es_ES-davefx-medium',
        'it' => 'it_IT-paola-medium',
        'ru' => 'ru_RU-dmitri-medium',
        'uk' => 'uk_UA-ukrainian_tts-medium',
    ])->and(array_keys(config('app.supported_locales')))->toEqualCanonicalizing(array_keys(config('services.piper.voices')));
});

test('synthesize runs piper without a shell, passes the text via stdin and stores a uuid wav on the private disk', function (string $locale, string $voice) {
    fakePiper(fakeWav(2.0));

    $result = app(PiperTtsService::class)->synthesize("Hallo Welt\n", $locale);

    Process::assertRan(function (PendingProcess $process) use ($voice) {
        return is_array($process->command)
            && $process->command[0] === '/opt/piper/venv/bin/python3'
            && array_slice($process->command, 1, 4) === ['-m', 'piper', '-m', $this->voices.'/'.$voice.'.onnx']
            && in_array('--data-dir', $process->command, true)
            && $process->input === 'Hallo Welt'
            && $process->timeout === 300
            && ! in_array('Hallo Welt', $process->command, true);
    });

    expect($result['path'])->toMatch('#^audio-briefings/[0-9a-f-]{36}\.wav$#')
        ->and($result['duration_ms'])->toBe(2000);
    Storage::disk('local')->assertExists($result['path']);
    expect(substr(Storage::disk('local')->get($result['path']), 0, 4))->toBe('RIFF');
})->with([
    ['de', 'de_DE-thorsten-high'],
    ['en', 'en_US-lessac-high'],
    ['es', 'es_ES-davefx-medium'],
    ['it', 'it_IT-paola-medium'],
    ['ru', 'ru_RU-dmitri-medium'],
    ['uk', 'uk_UA-ukrainian_tts-medium'],
]);

test('only whitelisted locales resolve to a voice, never user controlled model names', function (string $locale) {
    fakePiper(fakeWav());

    expect(fn () => app(PiperTtsService::class)->synthesize('Text', $locale))
        ->toThrow(PiperTtsException::class);
    Process::assertNothingRan();
})->with(['fr', '', '../../etc/passwd', 'de_DE-thorsten-high', 'de/../ru', 'DE']);

test('a configured voice with a traversal pattern is rejected', function () {
    config(['services.piper.voices.de' => '../../tmp/evil']);
    fakePiper(fakeWav());

    expect(fn () => app(PiperTtsService::class)->synthesize('Text', 'de'))->toThrow(PiperTtsException::class);
    Process::assertNothingRan();
});

test('a missing voice file fails with voice_missing', function () {
    unlink($this->voices.'/de_DE-thorsten-high.onnx');
    fakePiper(fakeWav());

    try {
        app(PiperTtsService::class)->synthesize('Text', 'de');
    } catch (PiperTtsException $exception) {
        expect($exception->errorCode)->toBe('voice_missing');
    }

    Process::assertNothingRan();
});

test('invalid text is rejected before any process starts', function (string $text) {
    fakePiper(fakeWav());

    try {
        app(PiperTtsService::class)->synthesize($text, 'de');
        $this->fail('Exception erwartet');
    } catch (PiperTtsException $exception) {
        expect($exception->errorCode)->toBe('invalid_text');
    }

    Process::assertNothingRan();
})->with([
    'empty' => '',
    'whitespace' => " \n\t ",
    'too long' => fn () => str_repeat('a', PiperTtsService::MAX_TEXT_LENGTH + 1),
    'null byte' => "Hallo\0Welt",
    'broken utf-8' => "Hallo \xC3\x28",
]);

test('text of exactly the maximum length is accepted', function () {
    fakePiper(fakeWav());

    $result = app(PiperTtsService::class)->synthesize(str_repeat('a', PiperTtsService::MAX_TEXT_LENGTH), 'de');

    expect($result['duration_ms'])->toBeGreaterThan(0);
});

test('a failing process is reported generically, nothing is stored and the temp file is removed', function () {
    $before = glob(sys_get_temp_dir().'/nexoma-tts-*') ?: [];
    fakePiper('', 1);

    try {
        app(PiperTtsService::class)->synthesize('Geheimer Sprechtext', 'de');
        $this->fail('Exception erwartet');
    } catch (PiperTtsException $exception) {
        expect($exception->errorCode)->toBe('piper_failed')
            ->and($exception->getMessage())->not->toContain('Geheimer');
    }

    expect(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(glob(sys_get_temp_dir().'/nexoma-tts-*') ?: [])->toBe($before);
});

test('output that is not a valid wav is rejected', function (string $content) {
    fakePiper($content);

    try {
        app(PiperTtsService::class)->synthesize('Text', 'de');
        $this->fail('Exception erwartet');
    } catch (PiperTtsException $exception) {
        expect($exception->errorCode)->toBe('invalid_audio');
    }

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
})->with([
    'empty' => '',
    'plain text' => fn () => str_repeat('x', 200),
    'wrong magic' => fn () => 'RIFX'.substr(fakeWav(), 4),
    'not wave' => fn () => substr_replace(fakeWav(), 'AVI ', 8, 4),
    'no data chunk' => fn () => substr(fakeWav(), 0, 36).str_repeat("\0", 20),
]);

test('the temp file is removed also after a successful run', function () {
    $before = glob(sys_get_temp_dir().'/nexoma-tts-*') ?: [];
    fakePiper(fakeWav());

    app(PiperTtsService::class)->synthesize('Text', 'de');

    expect(glob(sys_get_temp_dir().'/nexoma-tts-*') ?: [])->toBe($before);
});

test('a timeout is reported as piper_timeout', function () {
    Process::fake(fn () => throw new ProcessTimedOutException(
        new Symfony\Component\Process\Exception\ProcessTimedOutException(
            new Symfony\Component\Process\Process(['x']),
            Symfony\Component\Process\Exception\ProcessTimedOutException::TYPE_GENERAL,
        ),
        Process::result(),
    ));

    try {
        app(PiperTtsService::class)->synthesize('Text', 'de');
        $this->fail('Exception erwartet');
    } catch (PiperTtsException $exception) {
        expect($exception->errorCode)->toBe('piper_timeout');
    }
});

test('the audio briefing model keeps owner and status out of mass assignment and tracks its state', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $briefing = $user->audioBriefings()->create([
        'locale' => 'de', 'content_hash' => str_repeat('a', 64), 'speech_text' => 'Text',
        'user_id' => $other->id, 'status' => 'ready', 'storage_path' => '../x', 'duration_ms' => 5,
    ]);

    expect($briefing->fresh()->user_id)->toBe($user->id)
        ->and($briefing->fresh()->status)->toBe(AudioBriefing::STATUS_PENDING)
        ->and($briefing->fresh()->storage_path)->toBeNull()
        ->and($briefing->isReady())->toBeFalse();

    $briefing->markReady('audio-briefings/x.wav', 1234);
    expect($briefing->fresh()->isReady())->toBeTrue()->and($briefing->fresh()->duration_ms)->toBe(1234);

    $briefing->markFailed('piper_failed');
    expect($briefing->fresh()->status)->toBe(AudioBriefing::STATUS_FAILED)->and($briefing->fresh()->error_code)->toBe('piper_failed');
});

test('a briefing content hash is unique per user but may repeat across users', function () {
    $hash = str_repeat('b', 64);
    $user = User::factory()->create();
    AudioBriefing::factory()->for($user)->create(['content_hash' => $hash]);

    AudioBriefing::factory()->create(['content_hash' => $hash]);

    expect(fn () => AudioBriefing::factory()->for($user)->create(['content_hash' => $hash]))
        ->toThrow(QueryException::class);
});

test('deleting a user removes the briefings', function () {
    $briefing = AudioBriefing::factory()->ready()->create();

    $briefing->user->delete();

    expect(AudioBriefing::query()->count())->toBe(0);
});
