export type OAuthScope = {
    id: string;
    description: string;
};

/** Props der Consent-Seite (Whitelist; Client-Daten sind unvertrauenswuerdig und nur Text). */
export type AuthorizeProps = {
    client: { name: string };
    clientId: string;
    redirectHost: string;
    redirectPunycode: boolean;
    scopes: OAuthScope[];
    authToken: string;
    state: string | null;
    approveUrl: string;
    denyUrl: string;
    csrfToken: string;
    user: { email: string };
};

export type ConnectedApp = {
    id: string;
    name: string;
    redirectHosts: { host: string; punycode: boolean }[];
    connectedAt: string | null;
    scopes: string[];
};
