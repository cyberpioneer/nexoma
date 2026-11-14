/** Eine Rechnung aus Stripe (nur Whitelist-Felder; die Links führen zur von Stripe gehosteten Rechnung bzw. PDF). */
export type InvoiceSummary = {
    number: string | null;
    date: string | null;
    /** Gesamtbetrag in der kleinsten Währungseinheit (z. B. Cent). */
    total: number;
    currency: string;
    status: 'paid' | 'open' | 'void' | 'uncollectible';
    viewUrl: string | null;
    pdfUrl: string | null;
};
