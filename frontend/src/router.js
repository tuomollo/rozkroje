import { createRouter, createWebHistory } from 'vue-router'
import { restoreSession, state } from './stores/appStore'
import LoginView from './views/LoginView.vue'
import UploadView from './views/UploadView.vue'
import ProjectsView from './views/ProjectsView.vue'
import MaterialTypesView from './views/MaterialTypesView.vue'
import UsersView from './views/UsersView.vue'
import SettingsView from './views/SettingsView.vue'
import MaterialsView from './views/MaterialsView.vue'
import ClientsView from './views/ClientsView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/upload' },
    { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
    { path: '/upload', name: 'upload', component: UploadView, meta: { requiresAuth: true } },
    { path: '/projects', name: 'projects', component: ProjectsView, meta: { requiresAuth: true } },
    {
      path: '/material-types',
      name: 'material-types',
      component: MaterialTypesView,
      meta: { requiresAuth: true, adminOnly: true },
    },
    { path: '/users', name: 'users', component: UsersView, meta: { requiresAuth: true, adminOnly: true } },
    { path: '/settings', name: 'settings', component: SettingsView, meta: { requiresAuth: true, adminOnly: true } },
    { path: '/materials', name: 'materials', component: MaterialsView, meta: { requiresAuth: true } },
    { path: '/clients', name: 'clients', component: ClientsView, meta: { requiresAuth: true } },
  ],
})

router.beforeEach(async (to, from, next) => {
  if (to.name === 'login' && state.user) {
    return next({ name: 'upload' })
  }

  if (to.meta.requiresAuth) {
    try {
      await restoreSession()
    } catch (e) {
      return next({ name: 'login' })
    }

    if (!state.user) {
      return next({ name: 'login' })
    }

    if (to.meta.adminOnly && !state.user?.is_admin) {
      return next({ name: 'upload' })
    }
  }

  next()
})

export default router
