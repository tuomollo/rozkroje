import { computed, reactive } from 'vue'
import api, { setAuthToken } from '../services/api'

export const state = reactive({
  user: null,
  loading: false,
  authError: '',
  projects: [],
  materialTypes: [],
  users: [],
  settings: [],
  materials: [],
  materialsMeta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
})

export const isAdmin = computed(() => !!state.user?.is_admin)

const resetData = () => {
  state.projects = []
  state.materialTypes = []
  state.users = []
  state.settings = []
}

export const restoreSession = async () => {
  const token = localStorage.getItem('auth_token')
  if (!token) {
    state.user = null
    resetData()
    return
  }
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

export const loadSettings = async () => {
  const { data } = await api.get('/settings')
  state.settings = data
}

export const createSetting = async (payload) => {
  const { data } = await api.post('/settings', payload)
  state.settings.push(data)
  state.settings.sort((a, b) => a.key.localeCompare(b.key))
  return data
}

export const updateSetting = async (id, payload) => {
  const { data } = await api.put(`/settings/${id}`, payload)
  const index = state.settings.findIndex((item) => item.id === id)
  if (index !== -1) state.settings[index] = data
  state.settings.sort((a, b) => a.key.localeCompare(b.key))
  return data
}

export const deleteSetting = async (id) => {
  await api.delete(`/settings/${id}`)
  state.settings = state.settings.filter((item) => item.id !== id)
}

export const loadMaterials = async (page = 1, search = '', perPage = 10) => {
  const { data } = await api.get('/materials', {
    params: { page, search, per_page: perPage },
  })
  state.materials = data.data ?? data
  if (data.meta) {
    state.materialsMeta = data.meta
  } else {
    state.materialsMeta = {
      current_page: 1,
      last_page: 1,
      per_page: state.materials.length,
      total: state.materials.length,
    }
  }
}

export const createMaterial = async (payload) => {
  const { data } = await api.post('/materials', payload)
  await loadMaterials(state.materialsMeta.current_page, '', state.materialsMeta.per_page)
  return data
}

export const updateMaterial = async (id, payload) => {
  const { data } = await api.put(`/materials/${id}`, payload)
  await loadMaterials(state.materialsMeta.current_page, '', state.materialsMeta.per_page)
  return data
}

export const deleteMaterial = async (id) => {
  await api.delete(`/materials/${id}`)
  const page = state.materials.length === 1 && state.materialsMeta.current_page > 1
    ? state.materialsMeta.current_page - 1
    : state.materialsMeta.current_page
  await loadMaterials(page, '', state.materialsMeta.per_page)
}
