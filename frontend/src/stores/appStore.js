import { computed, reactive } from 'vue'
import api, { setAuthToken } from '../services/api'

export const state = reactive({
  user: null,
  loading: false,
  authError: '',
  projects: [],
  materialTypes: [],
  users: [],
})

export const isAdmin = computed(() => !!state.user?.is_admin)

const resetData = () => {
  state.projects = []
  state.materialTypes = []
  state.users = []
}

export const restoreSession = async () => {
  const token = localStorage.getItem('auth_token')
  if (!token) return
  try {
    const { data } = await api.get('/auth/me')
    state.user = data
    await bootstrapData()
  } catch (error) {
    setAuthToken(null)
    state.user = null
    resetData()
    throw error
  }
}

export const login = async (email, password) => {
  state.authError = ''
  state.loading = true
  try {
    const { data } = await api.post('/auth/login', { email, password })
    state.user = data.user
    setAuthToken(data.token)
    await bootstrapData()
  } catch (error) {
    state.authError = error.response?.data?.message ?? 'Logowanie nie powiodło się'
    throw error
  } finally {
    state.loading = false
  }
}

export const logout = () => {
  setAuthToken(null)
  state.user = null
  resetData()
}

export const bootstrapData = async () => {
  await Promise.all([loadProjects(), loadMaterialTypes()])
  if (isAdmin.value) {
    await loadUsers()
  }
}

export const loadProjects = async () => {
  const { data } = await api.get('/projects')
  state.projects = data
}

export const createProject = async (payload) => {
  const { data } = await api.post('/projects', payload)
  state.projects.unshift(data)
  return data
}

export const updateProject = async (projectId, payload) => {
  const { data } = await api.put(`/projects/${projectId}`, payload)
  const index = state.projects.findIndex((p) => p.id === projectId)
  if (index !== -1) state.projects[index] = data
  return data
}

export const deleteProject = async (projectId) => {
  await api.delete(`/projects/${projectId}`)
  state.projects = state.projects.filter((p) => p.id !== projectId)
}

export const loadMaterialTypes = async () => {
  const { data } = await api.get('/material-types')
  state.materialTypes = data
}

export const createMaterialType = async (payload) => {
  const { data } = await api.post('/material-types', payload)
  state.materialTypes.push(data)
  return data
}

export const updateMaterialType = async (typeId, payload) => {
  const { data } = await api.put(`/material-types/${typeId}`, payload)
  const index = state.materialTypes.findIndex((t) => t.id === typeId)
  if (index !== -1) state.materialTypes[index] = data
  return data
}

export const deleteMaterialType = async (typeId) => {
  await api.delete(`/material-types/${typeId}`)
  state.materialTypes = state.materialTypes.filter((t) => t.id !== typeId)
}

export const loadUsers = async () => {
  const { data } = await api.get('/users')
  state.users = data
}

export const createUser = async (payload) => {
  const { data } = await api.post('/users', payload)
  state.users.push(data)
  return data
}

export const updateUser = async (userId, payload) => {
  const { data } = await api.put(`/users/${userId}`, payload)
  const index = state.users.findIndex((u) => u.id === userId)
  if (index !== -1) state.users[index] = data
  return data
}

export const deleteUser = async (userId) => {
  await api.delete(`/users/${userId}`)
  state.users = state.users.filter((u) => u.id !== userId)
}
