import axios, { type AxiosRequestConfig } from 'axios'

// One Axios instance for the whole API. No baseURL: OpenAPI paths already
// include /api/v1, and in dev the Vite proxy makes them same-origin, so
// Sanctum's cookie/CSRF session auth works without cross-origin config.
export const http = axios.create({
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
  },
})

// Mutator used by the Orval-generated client: returns the response body so the
// generated hooks resolve to typed data.
export const customInstance = <T>(config: AxiosRequestConfig): Promise<T> =>
  http({ ...config }).then((response) => response.data)
