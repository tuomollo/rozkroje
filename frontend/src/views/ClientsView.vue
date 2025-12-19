<script setup>
import { onMounted, reactive, ref, computed, watch } from 'vue'
import { createClient, deleteClient, loadClients, state, updateClient } from '../stores/appStore'

const search = ref('')
const currentPage = ref(1)
const perPage = ref(10)
const showCreate = ref(false)
const statusMessage = ref('')
const newClient = reactive({ first_name: '', last_name: '', email: '', phone: '' })
const editClient = reactive({ id: null, first_name: '', last_name: '', email: '', phone: '' })

onMounted(async () => {
  if (!state.clients.length) {
    await loadClients()
  }
})

const filtered = computed(() => {
  const term = search.value.toLowerCase()
  if (!term) return state.clients
  return state.clients.filter((c) => {
    const full = `${c.first_name ?? ''} ${c.last_name ?? ''}`.toLowerCase()
    return full.includes(term) || (c.email ?? '').toLowerCase().includes(term) || (c.phone ?? '').toLowerCase().includes(term)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filtered.value.slice(start, start + perPage.value)
})

watch(search, () => {
  currentPage.value = 1
})

const submitNew = async () => {
  if (!newClient.first_name || !newClient.last_name) return
  statusMessage.value = ''
  try {
    await createClient(newClient)
    Object.assign(newClient, { first_name: '', last_name: '', email: '', phone: '' })
    showCreate.value = false
    statusMessage.value = 'Dodano klienta.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Nie udało się dodać klienta.'
  }
}

const startEdit = (item) => {
  Object.assign(editClient, {
    id: item.id,
    first_name: item.first_name,
    last_name: item.last_name,
    email: item.email ?? '',
    phone: item.phone ?? '',
  })
}

const submitEdit = async () => {
  if (!editClient.id) return
  statusMessage.value = ''
  try {
    await updateClient(editClient.id, {
      first_name: editClient.first_name,
      last_name: editClient.last_name,
      email: editClient.email,
      phone: editClient.phone,
    })
    statusMessage.value = 'Zaktualizowano.'
    editClient.id = null
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Aktualizacja nie powiodła się.'
  }
}

const removeItem = async (id) => {
  statusMessage.value = ''
  try {
    await deleteClient(id)
    statusMessage.value = 'Usunięto.'
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
</script>

<template>
  <section class="card card-wide">
    <div class="card-head">
      <div>
        <p class="eyebrow">Klienci</p>
        <h3>Lista i zarządzanie</h3>
      </div>
      <div class="actions">
        <input v-model="search" placeholder="Szukaj po imieniu, nazwisku, emailu..." />
        <button v-if="state.user?.is_admin" class="ghost" @click="showCreate = !showCreate">
          {{ showCreate ? 'Schowaj' : 'Nowy' }}
        </button>
      </div>
    </div>

    <form v-if="showCreate && state.user?.is_admin" class="form-grid" @submit.prevent="submitNew">
      <input v-model="newClient.first_name" placeholder="Imię" required />
      <input v-model="newClient.last_name" placeholder="Nazwisko" required />
      <input v-model="newClient.email" type="email" placeholder="Email (opcjonalnie)" />
      <input v-model="newClient.phone" placeholder="Telefon (opcjonalnie)" />
      <button type="submit">Dodaj</button>
    </form>

    <p v-if="statusMessage" class="hint">{{ statusMessage }}</p>

    <div class="list">
      <div v-for="client in paginated" :key="client.id" class="row">
        <div class="row-main">
          <strong>{{ client.first_name }} {{ client.last_name }}</strong>
          <span class="muted">{{ client.email || 'brak maila' }}</span>
          <span class="muted">{{ client.phone || 'brak telefonu' }}</span>
        </div>
        <div class="row-actions">
          <button v-if="state.user?.is_admin" class="ghost" @click="startEdit(client)">Edytuj</button>
          <button v-if="state.user?.is_admin" class="ghost danger" @click="removeItem(client.id)">Usuń</button>
        </div>
        <div v-if="state.user?.is_admin && editClient.id === client.id" class="inline-edit">
          <h4>Edytuj klienta</h4>
          <div class="inline-fields">
            <div class="inline-field">
              <label>Imię
                <input v-model="editClient.first_name" placeholder="Imię" />
              </label>
            </div>
            <div class="inline-field">
              <label>Nazwisko
                <input v-model="editClient.last_name" placeholder="Nazwisko" />
              </label>
            </div>
            <div class="inline-field">
              <label>Email
                <input v-model="editClient.email" type="email" placeholder="Email" />
              </label>
            </div>
            <div class="inline-field">
              <label>Telefon
                <input v-model="editClient.phone" placeholder="Telefon" />
              </label>
            </div>
            <div class="actions">
              <button @click="submitEdit">Zapisz</button>
              <button class="ghost" @click="editClient.id = null">Anuluj</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="pagination" v-if="totalPages > 1">
      <button class="ghost" @click="prevPage" :disabled="currentPage === 1">Poprzednia</button>
      <span>Strona {{ currentPage }} / {{ totalPages }}</span>
      <button class="ghost" @click="nextPage" :disabled="currentPage === totalPages">Następna</button>
    </div>

  </section>
</template>
