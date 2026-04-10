import { createRouter, createWebHistory } from 'vue-router'
import TrailerTrackingPage from '../views/TrailerTrackingPage.vue'

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            redirect: '/reports/trailer-tracking',
        },
        {
            path: '/reports/trailer-tracking',
            name: 'trailer-tracking',
            component: TrailerTrackingPage,
        },
    ],
})

export default router