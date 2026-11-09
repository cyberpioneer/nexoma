/** Formularwerte aus der URL (alle als Text, leer = null). */
export type JobFilters = {
    was: string | null;
    wo: string | null;
    umkreis: string | null;
    angebotsart: string | null;
    berufsfeld: string | null;
    veroeffentlichtseit: string | null;
};

export type OfferType =
    | 'ARBEIT'
    | 'SELBSTAENDIGKEIT'
    | 'AUSBILDUNG'
    | 'PRAKTIKUM_TRAINEE';

export type JobSummary = {
    refnr: string;
    title: string | null;
    profession: string | null;
    employer: string | null;
    location: string | null;
    distance: number | null;
    publishedAt: string | null;
    startDate: string | null;
    offerType: OfferType | null;
};

export type SearchResults =
    | { status: 'unavailable' }
    | {
          status: 'ok' | 'empty';
          total: number;
          jobs: JobSummary[];
          page: number;
          pages: number;
          previousUrl: string | null;
          nextUrl: string | null;
      };

export type JobDetails = {
    refnr: string;
    title: string | null;
    profession: string | null;
    employer: string | null;
    locations: string[];
    description: string | null;
    publishedAt: string | null;
    startDate: string | null;
    offerType: OfferType | null;
    contractDuration: 'BEFRISTET' | 'UNBEFRISTET' | null;
    workingTimes: ('fulltime' | 'parttime' | 'shift')[];
    homeOffice: boolean;
    officialUrl: string;
    applicationUrl: string | null;
    applicationHost: string | null;
};
