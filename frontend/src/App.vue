<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api, { setAuthToken } from './services/api'

const loginForm = reactive({
  email: 'admin@example.com',
  password: 'admin123',
})

const user = ref(null)
const loading = ref(false)
const authError = ref('')
const statusMessage = ref('')

const projects = ref([])
const materialTypes = ref([])
const users = ref([])

const newProject = reactive({ name: '', client_name: '' })
const editProjectForm = reactive({ id: null, name: '', client_name: '' })

const newMaterialType = reactive({ name: '' })
const editMaterialType = reactive({ id: null, name: '' })

const newUser = reactive({ name: '', email: '', password: '', is_admin: false })
const editUser = reactive({ id: null, name: '', email: '', password: '', is_admin: false })

const selectedProjectId = ref(null)
const selectedFile = ref(null)
const uploadToken = ref('')
const unknownMaterials = ref([])
const materialAssignments = reactive({})
const downloadUrl = ref('')

const isAdmin = computed(() => !!user.value?.is_admin)

const resetUploadState = () => {
  uploadToken.value = ''
  unknownMaterials.value = []
  Object.keys(materialAssignments).forEach((key) => delete materialAssignments[key])
  downloadUrl.value = ''
  statusMessage.value = ''
}

const bootstrapData = async () => {
  await Promise.all([loadMaterialTypes(), loadProjects()])
  if (isAdmin.value) {
    await loadUsers()
  }
}

const loadProjects = async () => {
  const { data } = await api.get('/projects')
  projects.value = data
  if (!selectedProjectId.value && projects.value.length) {
    selectedProjectId.value = projects.value[0].id
  }
}

const loadMaterialTypes = async () => {
  const { data } = await api.get('/material-types')
  materialTypes.value = data
}

const loadUsers = async () => {
  const { data } = await api.get('/users')
  users.value = data
}

const login = async () => {
  authError.value = ''
  loading.value = true
  try {
    const { data } = await api.post('/auth/login', {
      email: loginForm.email,
      password: loginForm.password,
    })
    user.value = data.user
    setAuthToken(data.token)
    await bootstrapData()
  } catch (error) {
    authError.value = error.response?.data?.message ?? 'Logowanie nie powiodło się'
  } finally {
    loading.value = false
  }
}

const logout = () => {
  setAuthToken(null)
  user.value = null
  projects.value = []
  materialTypes.value = []
  users.value = []
  resetUploadState()
}

const createProject = async () => {
  if (!newProject.name || !newProject.client_name) return
  const { data } = await api.post('/projects', newProject)
  projects.value.unshift(data)
  newProject.name = ''
  newProject.client_name = ''
}

const startEditProject = (project) => {
  editProjectForm.id = project.id
  editProjectForm.name = project.name
  editProjectForm.client_name = project.client_name
}

const updateProject = async () => {
  if (!editProjectForm.id) return
  const { data } = await api.put(`/projects/${editProjectForm.id}`, {
    name: editProjectForm.name,
    client_name: editProjectForm.client_name,
  })
  const index = projects.value.findIndex((p) => p.id === editProjectForm.id)
  if (index !== -1) projects.value[index] = data
  editProjectForm.id = null
}

const deleteProject = async (projectId) => {
  await api.delete(`/projects/${projectId}`)
  projects.value = projects.value.filter((p) => p.id !== projectId)
  if (selectedProjectId.value === projectId) {
    selectedProjectId.value = projects.value[0]?.id ?? null
  }
}

const createMaterialType = async () => {
  if (!newMaterialType.name) return
  const { data } = await api.post('/material-types', newMaterialType)
  materialTypes.value.push(data)
  newMaterialType.name = ''
}

const startEditMaterialType = (type) => {
  editMaterialType.id = type.id
  editMaterialType.name = type.name
}

const updateMaterialType = async () => {
  if (!editMaterialType.id) return
  const { data } = await api.put(`/material-types/${editMaterialType.id}`, {
    name: editMaterialType.name,
  })
  const index = materialTypes.value.findIndex((t) => t.id === editMaterialType.id)
  if (index !== -1) materialTypes.value[index] = data
  editMaterialType.id = null
}

const deleteMaterialType = async (typeId) => {
  await api.delete(`/material-types/${typeId}`)
  materialTypes.value = materialTypes.value.filter((t) => t.id !== typeId)
}

const createUser = async () => {
  if (!newUser.name || !newUser.email || !newUser.password) return
  const { data } = await api.post('/users', newUser)
  users.value.push(data)
  newUser.name = ''
  newUser.email = ''
  newUser.password = ''
  newUser.is_admin = false
}

const startEditUser = (item) => {
  editUser.id = item.id
  editUser.name = item.name
  editUser.email = item.email
  editUser.password = ''
  editUser.is_admin = !!item.is_admin
}

const updateUser = async () => {
  if (!editUser.id) return
  const payload = {
    name: editUser.name,
    email: editUser.email,
    is_admin: editUser.is_admin,
  }
  if (editUser.password) payload.password = editUser.password
  const { data } = await api.put(`/users/${editUser.id}`, payload)
  const index = users.value.findIndex((u) => u.id === editUser.id)
  if (index !== -1) users.value[index] = data
  editUser.id = null
}

const deleteUser = async (id) => {
  await api.delete(`/users/${id}`)
  users.value = users.value.filter((u) => u.id !== id)
}

const handleFileChange = (event) => {
  const [file] = event.target.files
  selectedFile.value = file
  resetUploadState()
}

const inspectUpload = async () => {
  if (!selectedProjectId.value || !selectedFile.value) return
  loading.value = true
  statusMessage.value = ''
  try {
    const form = new FormData()
    form.append('project_id', selectedProjectId.value)
    form.append('file', selectedFile.value)
    const { data } = await api.post('/uploads/inspect', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    uploadToken.value = data.upload_token
    unknownMaterials.value = data.unknown_materials
    data.unknown_materials.forEach((item) => {
      materialAssignments[item] = data.material_types[0]?.id ?? null
    })
    materialTypes.value = data.material_types
    if (unknownMaterials.value.length === 0) {
      statusMessage.value = 'Brak nieznanych materiałów — możesz od razu wygenerować pliki.'
    }
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Analiza pliku nie powiodła się.'
  } finally {
    loading.value = false
  }
}

const processUpload = async () => {
  if (!uploadToken.value) {
    statusMessage.value = 'Prześlij plik do wstępnej analizy.'
    return
  }

  const assignments = unknownMaterials.value.map((name) => ({
    name,
    material_type_id: materialAssignments[name],
  }))

  if (assignments.some((item) => !item.material_type_id)) {
    statusMessage.value = 'Wybierz typ dla każdego nowego materiału.'
    return
  }

  loading.value = true
  try {
    const { data } = await api.post('/uploads/process', {
      upload_token: uploadToken.value,
      project_id: selectedProjectId.value,
      assignments,
    })
    downloadUrl.value = data.download_url
    statusMessage.value = 'Pliki wygenerowane. Poniżej znajdziesz link do ZIP.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Generowanie nie powiodło się.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  const token = localStorage.getItem('auth_token')
  if (!token) return
  try {
    const { data } = await api.get('/auth/me')
    user.value = data
    await bootstrapData()
  } catch (error) {
    setAuthToken(null)
  }
})
</script>

<template>
  <div class="page">
    <header class="hero">
      <div>
        <p class="eyebrow">Skywood</p>
        <h1>Rozkroje</h1>
        <p class="lede">
          <!--Wgraj arkusz, przypisz typy materiałów i pobierz paczkę ZIP z osobnymi plikami dla każdego materiału.-->
        </p>
      </div>
      <div class="user-chip" v-if="user">
        <div>
          <div class="chip-name">{{ user.name }}</div>
          <div class="chip-role">{{ user.is_admin ? 'Administrator' : 'Użytkownik' }}</div>
        </div>
        <button class="ghost" @click="logout">Wyloguj</button>
      </div>
    </header>

    <main v-if="!user" class="panel">
      <h2>Zaloguj się</h2>
      <form class="form-grid" @submit.prevent="login">
        <label>
          <span>Email</span>
          <input v-model="loginForm.email" type="email" required placeholder="you@example.com" />
        </label>
        <label>
          <span>Hasło</span>
          <input v-model="loginForm.password" type="password" required placeholder="••••••••" />
        </label>
        <button type="submit" :disabled="loading">Zaloguj</button>
        <p v-if="authError" class="error">{{ authError }}</p>
      </form>
    </main>

    <main v-else class="grid">
      <section class="card card-wide">
        <div class="card-head">
          <div>
            <p class="eyebrow">Analiza pliku</p>
            <h3>Upload i przypisanie materiałów</h3>
          </div>
          <div class="tag">{{ projects.length }} projektów</div>
        </div>
        <div class="split">
          <div class="stack">
            <label>
              <span>Projekt</span>
              <select v-model.number="selectedProjectId">
                <option :value="null" disabled>Wybierz projekt</option>
                <option v-for="project in projects" :key="project.id" :value="project.id">
                  {{ project.client_name }} — {{ project.name }}
                </option>
              </select>
            </label>
            <label>
              <span>Plik XLS/XLSX</span>
              <input type="file" accept=".xls,.xlsx" @change="handleFileChange" />
            </label>
            <div class="actions">
              <button @click="inspectUpload" :disabled="!selectedProjectId || !selectedFile || loading">
                Sprawdź materiały
              </button>
              <button class="ghost" @click="processUpload" :disabled="loading || !uploadToken">
                Podziel arkusz i utwórz ZIP
              </button>
            </div>
            <p v-if="statusMessage" class="hint">{{ statusMessage }}</p>
            <p v-if="downloadUrl" class="success">
              Pliki gotowe: <a :href="downloadUrl" target="_blank" rel="noopener">pobierz ZIP</a>
            </p>
          </div>
          <div class="stack review">
            <h4>Nieznane materiały</h4>
            <p v-if="unknownMaterials.length === 0">Brak pozycji do uzupełnienia.</p>
            <div v-else class="unknown-list">
              <div v-for="name in unknownMaterials" :key="name" class="unknown-item">
                <div class="pill">{{ name }}</div>
                <select v-model.number="materialAssignments[name]">
                  <option :value="null" disabled>Wybierz typ</option>
                  <option v-for="type in materialTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card-head">
          <div>
            <p class="eyebrow">Projekty</p>
            <h3>Zarządzaj</h3>
          </div>
        </div>
        <form class="form-grid" @submit.prevent="createProject">
          <input v-model="newProject.name" placeholder="Nazwa projektu" required />
          <input v-model="newProject.client_name" placeholder="Nazwa klienta" required />
          <button type="submit">Dodaj</button>
        </form>
        <div class="list">
          <div v-for="project in projects" :key="project.id" class="row">
            <div class="row-main">
              <strong>{{ project.name }}</strong>
              <span class="muted">{{ project.client_name }}</span>
            </div>
            <div class="row-actions">
              <button class="ghost" @click="startEditProject(project)">Edytuj</button>
              <button class="ghost danger" @click="deleteProject(project.id)">Usuń</button>
            </div>
          </div>
        </div>
        <div v-if="editProjectForm.id" class="inline-edit">
          <h4>Edytuj projekt</h4>
          <input v-model="editProjectForm.name" placeholder="Nazwa" />
          <input v-model="editProjectForm.client_name" placeholder="Klient" />
          <div class="actions">
            <button @click="updateProject">Zapisz</button>
            <button class="ghost" @click="editProjectForm.id = null">Anuluj</button>
          </div>
        </div>
      </section>

      <section class="card" v-if="isAdmin">
        <div class="card-head">
          <div>
            <p class="eyebrow">Typy materiałów</p>
            <h3>CRUD dla administratora</h3>
          </div>
        </div>
        <form class="form-grid" @submit.prevent="createMaterialType">
          <input v-model="newMaterialType.name" placeholder="Nazwa typu" required />
          <button type="submit">Dodaj</button>
        </form>
        <div class="list">
          <div v-for="type in materialTypes" :key="type.id" class="row">
            <div class="row-main">
              <strong>{{ type.name }}</strong>
            </div>
            <div class="row-actions">
              <button class="ghost" @click="startEditMaterialType(type)">Edytuj</button>
              <button class="ghost danger" @click="deleteMaterialType(type.id)">Usuń</button>
            </div>
          </div>
        </div>
        <div v-if="editMaterialType.id" class="inline-edit">
          <h4>Edytuj typ</h4>
          <input v-model="editMaterialType.name" placeholder="Nazwa" />
          <div class="actions">
            <button @click="updateMaterialType">Zapisz</button>
            <button class="ghost" @click="editMaterialType.id = null">Anuluj</button>
          </div>
        </div>
      </section>

      <section class="card" v-if="isAdmin">
        <div class="card-head">
          <div>
            <p class="eyebrow">Użytkownicy</p>
            <h3>Konta i uprawnienia</h3>
          </div>
        </div>
        <form class="form-grid" @submit.prevent="createUser">
          <input v-model="newUser.name" placeholder="Imię i nazwisko" required />
          <input v-model="newUser.email" type="email" placeholder="Email" required />
          <input v-model="newUser.password" type="password" placeholder="Hasło" required />
          <label class="checkbox">
            <input type="checkbox" v-model="newUser.is_admin" />
            <span>Administrator</span>
          </label>
          <button type="submit">Dodaj</button>
        </form>
        <div class="list">
          <div v-for="item in users" :key="item.id" class="row">
            <div class="row-main">
              <strong>{{ item.name }}</strong>
              <span class="muted">{{ item.email }}</span>
              <span class="tag small" :class="{ danger: item.is_admin }">{{ item.is_admin ? 'Admin' : 'Użytkownik' }}</span>
            </div>
            <div class="row-actions">
              <button class="ghost" @click="startEditUser(item)">Edytuj</button>
              <button class="ghost danger" @click="deleteUser(item.id)">Usuń</button>
            </div>
          </div>
        </div>
        <div v-if="editUser.id" class="inline-edit">
          <h4>Edytuj użytkownika</h4>
          <input v-model="editUser.name" placeholder="Imię i nazwisko" />
          <input v-model="editUser.email" type="email" placeholder="Email" />
          <input v-model="editUser.password" type="password" placeholder="Nowe hasło (opcjonalnie)" />
          <label class="checkbox">
            <input type="checkbox" v-model="editUser.is_admin" />
            <span>Administrator</span>
          </label>
          <div class="actions">
            <button @click="updateUser">Zapisz</button>
            <button class="ghost" @click="editUser.id = null">Anuluj</button>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>
