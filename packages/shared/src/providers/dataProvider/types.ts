export interface IRes<T = any> {
  data: T;
  meta?: {
    total: number;
    current_page: number;
    per_page: number;
    last_page: number;
  };
}

export interface DataProviderConfig {
  apiUrl: string;
  getLocale?: () => string;
}
