import axios, { AxiosInstance } from 'axios';

export const createAxiosInstance = (getLocale?: () => string): AxiosInstance => {
  const instance = axios.create();

  const defaultHeaders = {
    accept: 'application/json, text/plain, */*',
    'content-type': 'application/json;charset=UTF-8',
  };

  instance.defaults.headers.get = { ...defaultHeaders };
  instance.defaults.headers.post = { ...defaultHeaders };
  instance.defaults.headers.put = { ...defaultHeaders };
  instance.defaults.headers.delete = { ...defaultHeaders };

  if (getLocale) {
    instance.interceptors.request.use(
      (config) => {
        const locale = getLocale();
        const backendLocale = locale.replace('-', '_');
        config.headers = config.headers || {};
        config.headers['X-Locale'] = backendLocale;
        return config;
      },
      (error) => Promise.reject(error)
    );
  }

  return instance;
};
