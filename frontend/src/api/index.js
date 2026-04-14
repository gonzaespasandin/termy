import axios from 'axios';

const api = axios.create({
    baseURL: 'http://192.168.0.51:8000/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Interceptor para agregar token en cada request
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Servicios (público)
export const getServices = () => api.get('/services');

// CRUD Servicios (admin)
export const createService = (data) => api.post('/admin/services', data);
export const updateService = (id, data) => api.put(`/admin/services/${id}`, data);
export const deleteService = (id) => api.delete(`/admin/services/${id}`);

// CRUD Usuarios (admin)
export const getUsers = () => api.get('/admin/users');
export const createUser = (data) => api.post('/admin/users', data);
export const updateUser = (id, data) => api.put(`/admin/users/${id}`, data);
export const deleteUser = (id) => api.delete(`/admin/users/${id}`);

// CRUD Totems/Perfiles (admin)
export const getTotems = () => api.get('/admin/totems');
export const createTotem = (data) => {
    const config = data instanceof FormData ? {
        headers: { 'Content-Type': 'multipart/form-data' }
    } : {};
    return api.post('/admin/totems', data, config);
};
export const updateTotem = (id, data) => {
    if (data instanceof FormData) {
        data.append('_method', 'PUT');
        return api.post(`/admin/totems/${id}`, data, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
    }
    return api.put(`/admin/totems/${id}`, data);
};
export const deleteTotem = (id) => api.delete(`/admin/totems/${id}`);

// CRUD Ventanillas (admin)
export const getWindows = () => api.get('/admin/windows');
export const createWindow = (data) => api.post('/admin/windows', data);
export const updateWindow = (id, data) => api.put(`/admin/windows/${id}`, data);
export const deleteWindow = (id) => api.delete(`/admin/windows/${id}`);

// Obtener ventanilla por IP (operador)
export const getWindowByIp = () => api.get('/windows/by-ip');

// Dashboard (admin)
export const getDashboardStats = () => api.get('/admin/dashboard/stats');

// Historial (admin)
export const getHistory = (params) => api.get('/admin/history', { params });
export const getHistoryDetail = (id) => api.get(`/admin/history/${id}`);
export const getOperators = () => api.get('/admin/history/operators');

// Totems (público)
export const getTotemServices = (code) => api.get(`/totems/${code}/services`);

// Turnos
export const createTurn = (data) => api.post('/turns', data);
export const getQueue = () => api.get('/turns/queue'); // Público (pantalla de espera)
export const getOperatorQueue = () => api.get('/operator/queue'); // Protegido (operador)

// Acciones de turnos (operador)
export const callTurn = (id) => api.post(`/turns/${id}/call`);
export const attendTurn = (id) => api.post(`/turns/${id}/attend`);
export const completeTurn = (id) => api.post(`/turns/${id}/complete`);

// Auth
export const login = (credentials) => api.post('/login', credentials);
export const logout = () => api.post('/logout');
export const getUser = () => api.get('/user');

export default api;