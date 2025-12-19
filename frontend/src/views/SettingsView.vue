<script setup>
import { reactive, ref } from 'vue'
import { createSetting, deleteSetting, loadSettings, state, updateSetting } from '../stores/appStore'

const newSetting = reactive({ key: '', value: '' })
const editSetting = reactive({ id: null, key: '', value: '' })
const statusMessage = ref('')
const showCreate = ref(false)

const ensureData = async () => {
  if (!state.settings.length) {
    await loadSettings()
  }
}

ensureData()

const createItem = async () => {
  if (!newSetting.key) return
  statusMessage.value = ''
  try {
    await createSetting(newSetting)
    newSetting.key = ''
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
  editSetting.value = setting.value ?? ''
}

const updateItem = async () => {
  if (!editSetting.id) return
  statusMessage.value = ''
  try {
    await updateSetting(editSetting.id, { key: editSetting.key, value: editSetting.value })
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
</script>

<template>
  <section class="card">
    <div class="card-head">
      <div>
        <p class="eyebrow">Ustawienia</p>
        <h3>Parametry programu - nie ruszać</h3>
      </div>
      <button class="ghost" @click="showCreate = !showCreate">
        {{ showCreate ? 'Schowaj' : 'Nowy' }}
      </button>
    </div>

    <form v-if="showCreate" class="form-grid" @submit.prevent="createItem">
      <input v-model="newSetting.key" placeholder="Klucz" required />
      <input v-model="newSetting.value" placeholder="Wartość" />
      <button type="submit">Dodaj</button>
    </form>

    <p v-if="statusMessage" class="hint">{{ statusMessage }}</p>

    <div class="list">
      <div v-for="item in state.settings" :key="item.id" class="row">
        <div class="row-main">
          <strong>{{ item.key }}</strong>
          <span class="muted">{{ item.value }}</span>
        </div>
        <div class="row-actions">
          <button class="ghost" @click="startEdit(item)">Edytuj</button>
          <button class="ghost danger" @click="removeItem(item.id)">Usuń</button>
        </div>
      </div>
    </div>

    <div v-if="editSetting.id" class="inline-edit">
      <h4>Edytuj ustawienie</h4>
      <input v-model="editSetting.key" placeholder="Klucz" />
      <input v-model="editSetting.value" placeholder="Wartość" />
      <div class="actions">
        <button @click="updateItem">Zapisz</button>
        <button class="ghost" @click="editSetting.id = null">Anuluj</button>
      </div>
    </div>
  </section>
</template>
