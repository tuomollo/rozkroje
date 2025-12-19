<script setup>
import { reactive, ref } from 'vue'
import { createProject, deleteProject, state, updateProject } from '../stores/appStore'

const newProject = reactive({ name: '', client_name: '' })
const editProjectForm = reactive({ id: null, name: '', client_name: '' })
const statusMessage = ref('')

const createItem = async () => {
  if (!newProject.name || !newProject.client_name) return
  statusMessage.value = ''
  try {
    await createProject(newProject)
    newProject.name = ''
    newProject.client_name = ''
    statusMessage.value = 'Projekt dodany.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Nie udało się dodać projektu.'
  }
}

const startEdit = (project) => {
  editProjectForm.id = project.id
  editProjectForm.name = project.name
  editProjectForm.client_name = project.client_name
}

const updateItem = async () => {
  if (!editProjectForm.id) return
  statusMessage.value = ''
  try {
    await updateProject(editProjectForm.id, {
      name: editProjectForm.name,
      client_name: editProjectForm.client_name,
    })
    statusMessage.value = 'Projekt zaktualizowany.'
    editProjectForm.id = null
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Aktualizacja nie powiodła się.'
  }
}

const removeProject = async (projectId) => {
  statusMessage.value = ''
  try {
    await deleteProject(projectId)
    statusMessage.value = 'Projekt usunięty.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Usuwanie nie powiodło się.'
  }
}
</script>

<template>
  <section class="card">
    <div class="card-head">
      <div>
        <p class="eyebrow">Projekty</p>
        <h3>Zarządzaj</h3>
      </div>
    </div>
    <form class="form-grid" @submit.prevent="createItem">
      <input v-model="newProject.name" placeholder="Nazwa projektu" required />
      <input v-model="newProject.client_name" placeholder="Nazwa klienta" required />
      <button type="submit">Dodaj</button>
    </form>
    <p v-if="statusMessage" class="hint">{{ statusMessage }}</p>
    <div class="list">
      <div v-for="project in state.projects" :key="project.id" class="row">
        <div class="row-main">
          <strong>{{ project.name }}</strong>
          <span class="muted">{{ project.client_name }}</span>
        </div>
        <div class="row-actions">
          <button class="ghost" @click="startEdit(project)">Edytuj</button>
          <button class="ghost danger" @click="removeProject(project.id)">Usuń</button>
        </div>
      </div>
    </div>
    <div v-if="editProjectForm.id" class="inline-edit">
      <h4>Edytuj projekt</h4>
      <input v-model="editProjectForm.name" placeholder="Nazwa" />
      <input v-model="editProjectForm.client_name" placeholder="Klient" />
      <div class="actions">
        <button @click="updateItem">Zapisz</button>
        <button class="ghost" @click="editProjectForm.id = null">Anuluj</button>
      </div>
    </div>
  </section>
</template>
