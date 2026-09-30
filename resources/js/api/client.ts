import axios from 'axios'

const client = axios.create({
  baseURL: '/api',
  timeout: 15000,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

client.interceptors.response.use(
  (res) => res,
  (err) => {
    const msg = err?.response?.data?.error || err?.response?.data?.message || err.message
    return Promise.reject(new Error(msg))
  },
)

export default client
