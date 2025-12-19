<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { createSetting, deleteSetting, loadSettings, state, updateSetting } from '../stores/appStore'

const newSetting = reactive({ key: '', friendly_name: '', value: '' })
const editSetting = reactive({ id: null, key: '', friendly_name: '', value: '' })
const statusMessage = ref('')
const showCreate = ref(false)
const search = ref('')
const currentPage = ref(1)
const perPage = ref(10)

const ensureData = async () => {
  if (!state.settings.length) {
    await loadSettings()
  }
}

ensureData()

const filteredSettings = computed(() => {
  const term = search.value.toLowerCase()
  if (!term) return state.settings
  return state.settings.filter((item) => {
    const key = item.key?.toLowerCase() ?? ''
    const friendly = item.friendly_name?.toLowerCase() ?? ''
    return key.includes(term) || friendly.includes(term)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredSettings.value.length / perPage.value)))
const paginatedSettings = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredSettings.value.slice(start, start + perPage.value)
})

watch(search, () => {
  currentPage.value = 1
})

const createItem = async () => {
  if (!newSetting.key) return
  statusMessage.value = ''
  try {
    await createSetting(newSetting)
    newSetting.key = ''
    newSetting.friendly_name = ''
    newSetting.value = ''
    showCreate.value = false
    statusMessage.value = 'Dodano ustawienie.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Nie udało się dodać ustawienia.'
  }
}

const startEdit = (setting) => {
  editSetting.id = setting.id
  editSetting.key = setting.key
  editSetting.friendly_name = setting.friendly_name ?? ''
  editSetting.value = setting.value ?? ''
}

const updateItem = async () => {
  if (!editSetting.id) return
  statusMessage.value = ''
  try {
    await updateSetting(editSetting.id, {
      key: editSetting.key,
      friendly_name: editSetting.friendly_name,
      value: editSetting.value,
    })
    statusMessage.value = 'Zaktualizowano.'
    editSetting.id = null
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Aktualizacja nie powiodła się.'
  }
}

const removeItem = async (id) => {
  statusMessage.value = ''
  try {
    await deleteSetting(id)
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
  <section class="card">
    <div class="card-head">
      <div>
        <p class="eyebrow">Ustawienia</p>
        <h3>Parametry programu - nie ruszać</h3>
      </div>
      <div class="actions">
        <input v-model="search" placeholder="Szukaj po nazwie/kluczu..." />
        <button class="ghost" @click="showCreate = !showCreate">
          {{ showCreate ? 'Schowaj' : 'Nowy' }}
        </button>
      </div>
    </div>

    <form v-if="showCreate" class="form-grid" @submit.prevent="createItem">
      <input v-model="newSetting.key" placeholder="Klucz" required />
      <input v-model="newSetting.friendly_name" placeholder="Przyjazna nazwa" />
      <input v-model="newSetting.value" placeholder="Wartość" />
      <button type="submit">Dodaj</button>
    </form>

    <p v-if="statusMessage" class="hint">{{ statusMessage }}</p>

    <div class="list">
      <div v-for="item in paginatedSettings" :key="item.id" class="row">
        <div class="row-main">
          <strong>{{ item.friendly_name || item.key }}</strong>
          <span class="muted">{{ item.key }}</span>
          <span class="muted">{{ item.value }}</span>
        </div>
        <div class="row-actions">
          <button class="ghost" @click="startEdit(item)">Edytuj</button>
          <button class="ghost danger" @click="removeItem(item.id)">Usuń</button>
        </div>
      </div>
    </div>

    <div class="pagination" v-if="totalPages > 1">
      <button class="ghost" @click="prevPage" :disabled="currentPage === 1">Poprzednia</button>
      <span>Strona {{ currentPage }} / {{ totalPages }}</span>
      <button class="ghost" @click="nextPage" :disabled="currentPage === totalPages">Następna</button>
    </div>

    <div v-if="editSetting.id" class="inline-edit">
      <h4>Edytuj ustawienie</h4>
      <input v-model="editSetting.key" placeholder="Klucz" />
      <input v-model="editSetting.friendly_name" placeholder="Przyjazna nazwa" />
      <input v-model="editSetting.value" placeholder="Wartość" />
      <div class="actions">
        <button @click="updateItem">Zapisz</button>
        <button class="ghost" @click="editSetting.id = null">Anuluj</button>
      </div>
    </div>
  </section>
</template>
