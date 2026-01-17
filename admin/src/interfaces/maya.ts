export interface Account {
    phone?: string;
    password?: string;
    expiresChallengeId?: string;
    accessToken?: string;
    appToken?: string;
    profile?: {
        firstName: string;
        lastName: string;
    };
}
