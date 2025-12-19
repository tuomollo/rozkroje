<script setup>
import { reactive, ref } from 'vue'
import { createUser, deleteUser, state, updateUser } from '../stores/appStore'

const newUser = reactive({ name: '', email: '', password: '', is_admin: false })
const editUserForm = reactive({ id: null, name: '', email: '', password: '', is_admin: false })
const statusMessage = ref('')
const showCreate = ref(false)

const createItem = async () => {
  if (!newUser.name || !newUser.email || !newUser.password) return
  statusMessage.value = ''
  try {
    await createUser(newUser)
    newUser.name = ''
    newUser.email = ''
    newUser.password = ''
    newUser.is_admin = false
    showCreate.value = false
    statusMessage.value = 'Użytkownik dodany.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Nie udało się dodać użytkownika.'
  }
}

const startEdit = (item) => {
  editUserForm.id = item.id
  editUserForm.name = item.name
  editUserForm.email = item.email
  editUserForm.password = ''
  editUserForm.is_admin = !!item.is_admin
}

const updateItem = async () => {
  if (!editUserForm.id) return
  statusMessage.value = ''
  try {
    const payload = {
      name: editUserForm.name,
      email: editUserForm.email,
      is_admin: editUserForm.is_admin,
    }
    if (editUserForm.password) payload.password = editUserForm.password
    await updateUser(editUserForm.id, payload)
    statusMessage.value = 'Użytkownik zaktualizowany.'
    editUserForm.id = null
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Aktualizacja nie powiodła się.'
  }
}

const removeItem = async (id) => {
  if (!window.confirm('Czy jesteś pewien, że chcesz usunąć ten element?')) return
  statusMessage.value = ''
  try {
    await deleteUser(id)
    statusMessage.value = 'Użytkownik usunięty.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Usuwanie nie powiodło się.'
  }
}
</script>

<template>
  <section class="card">
    <div class="card-head">
      <div>
        <p class="eyebrow">Użytkownicy</p>
        <h3>Konta i uprawnienia</h3>
      </div>
      <button class="ghost" @click="showCreate = !showCreate">
        {{ showCreate ? 'Schowaj' : '➕' }}
      </button>
    </div>
    <form v-if="showCreate" class="form-grid" @submit.prevent="createItem">
      <input v-model="newUser.name" placeholder="Imię i nazwisko" required />
      <input v-model="newUser.email" type="email" placeholder="Email" required />
      <input v-model="newUser.password" type="password" placeholder="Hasło" required />
      <label class="checkbox">
        <input type="checkbox" v-model="newUser.is_admin" />
        <span>Administrator</span>
      </label>
      <button type="submit">Nowy</button>
    </form>
    <p v-if="statusMessage" class="hint">{{ statusMessage }}</p>
    <div class="list">
      <div v-for="item in state.users" :key="item.id" class="row">
        <div class="row-main">
          <strong>{{ item.name }}</strong>
          <span class="muted">{{ item.email }}</span>
          <span class="tag small" :class="{ danger: item.is_admin }">
            {{ item.is_admin ? 'Admin' : 'Użytkownik' }}
          </span>
        </div>
        <div class="row-actions">
          <button class="ghost" @click="startEdit(item)">🖉</button>
          <button class="ghost danger" @click="removeItem(item.id)">🗑️</button>
        </div>
        <div v-if="editUserForm.id === item.id" class="inline-edit">
          <h4>Edytuj użytkownika</h4>
          <div class="inline-fields">
            <div class="inline-field">
              <label>Imię i nazwisko
                <input v-model="editUserForm.name" placeholder="Imię i nazwisko" />
              </label>
            </div>
            <div class="inline-field">
              <label>Email
                <input v-model="editUserForm.email" type="email" placeholder="Email" />
              </label>
            </div>
            <div class="inline-field">
              <label>Nowe hasło
                <input v-model="editUserForm.password" type="password" placeholder="Nowe hasło (opcjonalnie)" />
              </label>
            </div>
            <div class="inline-field">
              <label class="checkbox">
                <input type="checkbox" v-model="editUserForm.is_admin" />
                <span>Administrator</span>
              </label>
            </div>
            <div class="actions">
              <button @click="updateItem">Zapisz</button>
              <button class="ghost" @click="editUserForm.id = null">Anuluj</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
