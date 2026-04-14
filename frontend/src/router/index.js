import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import DisplayView from '../views/DisplayView.vue';
import OperatorView from '../views/OperatorView.vue';
import LoginView from '../views/LoginView.vue';
import AdminView from '../views/AdminView.vue';

const routes = [
    { 
        path: '/',
        name: 'Home',
        component: HomeView
    },
    { 
        path: '/display',
        name: 'Display',
        component: DisplayView
    },
    {
        path: '/login',
        name: 'Login',
        component: LoginView
    },
    { 
        path: '/operator',
        name: 'Operator',
        component: OperatorView,
        meta: { requiresAuth: true }
    },
    { 
        path: '/admin',
        name: 'Admin',
        component: AdminView,
        meta: { requiresAuth: true, requiresAdmin: true }
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Guard para rutas protegidas
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token');
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    
    if (to.meta.requiresAuth && !token) {
        next('/login');
    } else if (to.meta.requiresAdmin && user.role !== 'admin') {
        next('/operator');
    } else if (to.path === '/login' && token) {
        // Redirigir según rol
        if (user.role === 'admin') {
            next('/admin');
        } else {
            next('/operator');
        }
    } else {
        next();
    }
});

export default router;