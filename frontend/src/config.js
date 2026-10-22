// Single source of truth for backend URLs.
// VITE_API_URL may be absolute (http://host:8000/api) or relative (/api, same origin).
export const API_URL = (import.meta.env.VITE_API_URL || '/api').replace(/\/+$/, '');

// Public storage (logos, uploads) is served next to the API, outside the /api prefix.
export const STORAGE_URL = `${API_URL.replace(/\/api$/, '')}/storage`;
