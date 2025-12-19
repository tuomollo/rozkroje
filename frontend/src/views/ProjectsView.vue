<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { createProject, deleteProject, loadClients, state, updateProject } from '../stores/appStore'

const newProject = reactive({ name: '', client_id: null })
const editProjectForm = reactive({ id: null, name: '', client_id: null })
const statusMessage = ref('')
const showCreate = ref(false)
const currentPage = ref(1)
const pageSize = 10
const search = ref('')

const filteredProjects = computed(() => {
  const term = search.value.toLowerCase()
  if (!term) return state.projects
  return state.projects.filter((project) => {
    const name = project.name?.toLowerCase() ?? ''
    const client = project.client?.full_name?.toLowerCase() ?? ''
    return name.includes(term) || client.includes(term)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredProjects.value.length / pageSize)))
const paginatedProjects = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredProjects.value.slice(start, start + pageSize)
})

watch(
  () => filteredProjects.value.length,
  () => {
    if (currentPage.value > totalPages.value) {
      currentPage.value = totalPages.value
    }
  },
)

watch(search, () => {
  currentPage.value = 1
})

const createItem = async () => {
  if (!newProject.name || !newProject.client_id) return
  statusMessage.value = ''
  try {
    await createProject(newProject)
    newProject.name = ''
    newProject.client_id = null
    showCreate.value = false
    statusMessage.value = 'Projekt dodany.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Nie udało się dodać projektu.'
  }
}

const startEdit = (project) => {
  editProjectForm.id = project.id
  editProjectForm.name = project.name
  editProjectForm.client_id = project.client_id
}

const updateItem = async () => {
  if (!editProjectForm.id) return
  statusMessage.value = ''
  try {
    await updateProject(editProjectForm.id, {
      name: editProjectForm.name,
      client_id: editProjectForm.client_id,
    })
    statusMessage.value = 'Projekt zaktualizowany.'
    editProjectForm.id = null
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Aktualizacja nie powiodła się.'
  }
}

const removeProject = async (projectId) => {
  if (!window.confirm('Czy jesteś pewien, że chcesz usunąć ten element?')) return
  statusMessage.value = ''
  try {
    await deleteProject(projectId)
    statusMessage.value = 'Projekt usunięty.'
    if ((currentPage.value - 1) * pageSize >= state.projects.length && currentPage.value > 1) {
      currentPage.value -= 1
    }
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Usuwanie nie powiodło się.'
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) currentPage.value += 1
}

const prevPage = () => {
  if (currentPage.value > 1) currentPage.value -= 1
}

const clientLabel = (project) => project.client?.full_name || 'Brak klienta'

const ensureClients = async () => {
  if (!state.clients.length) {
    await loadClients()
  }
}

ensureClients()
</script>

<template>
  <section class="card">
    <div class="card-head">
      <div>
        <p class="eyebrow">Projekty</p>
        <h3>Zarządzaj</h3>
      </div>
      <div class="actions">
        <input v-model="search" placeholder="Szukaj po projekcie lub kliencie..." />
        <button v-if="state.user?.is_admin" class="ghost" @click="showCreate = !showCreate">
          {{ showCreate ? 'Schowaj' : '➕' }}
        </button>
      </div>
    </div>
    <form v-if="showCreate && state.user?.is_admin" class="form-grid" @submit.prevent="createItem">
      <input v-model="newProject.name" placeholder="Nazwa projektu" required />
      <select v-model.number="newProject.client_id" required>
        <option :value="null" disabled>Wybierz klienta</option>
        <option v-for="client in state.clients" :key="client.id" :value="client.id">
          {{ client.first_name }} {{ client.last_name }}
        </option>
      </select>
      <button type="submit">Nowy</button>
    </form>
    <p v-if="statusMessage" class="hint">{{ statusMessage }}</p>
    <div class="list">
      <div v-for="project in paginatedProjects" :key="project.id" class="row">
        <div class="row-main">
          <strong>{{ project.name }}</strong>
          <span class="muted">{{ clientLabel(project) }}</span>
        </div>
        <div class="row-actions">
          <button v-if="state.user?.is_admin" class="ghost" @click="startEdit(project)">🖉</button>
          <button v-if="state.user?.is_admin" class="ghost danger" @click="removeProject(project.id)">🗑️</button>
        </div>
        <div v-if="state.user?.is_admin && editProjectForm.id === project.id" class="inline-edit">
          <h4>Edytuj projekt</h4>
          <div class="inline-fields">
            <div class="inline-field">
              <label>Nazwa
                <input v-model="editProjectForm.name" placeholder="Nazwa" />
              </label>
            </div>
            <div class="inline-field">
              <label>Klient
                <select v-model.number="editProjectForm.client_id">
                  <option v-for="client in state.clients" :key="client.id" :value="client.id">
                    {{ client.first_name }} {{ client.last_name }}
                  </option>
                </select>
              </label>
            </div>
            <div class="actions">
              <button @click="updateItem">Zapisz</button>
              <button class="ghost" @click="editProjectForm.id = null">Anuluj</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="pagination" v-if="state.projects.length > pageSize">
      <button class="ghost" @click="prevPage" :disabled="currentPage === 1">Poprzednia</button>
      <span>Strona {{ currentPage }} / {{ totalPages }}</span>
      <button class="ghost" @click="nextPage" :disabled="currentPage === totalPages">Następna</button>
    </div>
  </section>
</template>
