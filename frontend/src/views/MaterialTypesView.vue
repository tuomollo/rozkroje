<script setup>
import { reactive, ref } from 'vue'
import { createMaterialType, deleteMaterialType, state, updateMaterialType } from '../stores/appStore'

const newMaterialType = reactive({ name: ''})
const editMaterialType = reactive({ id: null, name: ''})
const statusMessage = ref('')
const showCreate = ref(false)

const createItem = async () => {
  if (!newMaterialType.name) return
  statusMessage.value = ''
  try {
    await createMaterialType({ name: newMaterialType.name})
    newMaterialType.name = ''
    showCreate.value = false
    statusMessage.value = 'Typ dodany.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Nie udało się dodać typu.'
  }
}

const startEdit = (type) => {
  editMaterialType.id = type.id
  editMaterialType.name = type.name
}

const updateItem = async () => {
  if (!editMaterialType.id) return
  statusMessage.value = ''
  try {
    await updateMaterialType(editMaterialType.id, {
      name: editMaterialType.name,
    })
    statusMessage.value = 'Typ zaktualizowany.'
    editMaterialType.id = null
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Aktualizacja nie powiodła się.'
  }
}

const removeItem = async (typeId) => {
  if (!window.confirm('Czy jesteś pewien, że chcesz usunąć ten element?')) return
  statusMessage.value = ''
  try {
    await deleteMaterialType(typeId)
    statusMessage.value = 'Typ usunięty.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Usuwanie nie powiodło się.'
  }
}
</script>

<template>
  <section class="card">
    <div class="card-head">
      <div>
        <p class="eyebrow">Typy materiałów</p>
        <h3>Zarządzanie materiałami - uwaga, nie popsuć!</h3>
      </div>
      <button class="ghost" @click="showCreate = !showCreate">
        {{ showCreate ? 'Schowaj' : '➕' }}
      </button>
    </div>
    <form v-if="showCreate" class="form-grid" @submit.prevent="createItem">
      <input v-model="newMaterialType.name" placeholder="Nazwa typu" required />
      <button type="submit">Nowy</button>
    </form>
    <p v-if="statusMessage" class="hint">{{ statusMessage }}</p>
    <div class="list">
      <div v-for="type in state.materialTypes" :key="type.id" class="row">
        <div class="row-main">
          <strong>{{ type.name }}</strong>
        </div>
        <div class="row-actions">
          <button class="ghost" @click="startEdit(type)">🖉</button>
          <button class="ghost danger" @click="removeItem(type.id)">🗑️</button>
        </div>
        <div v-if="editMaterialType.id === type.id" class="inline-edit">
          <h4>Edytuj typ</h4>
          <div class="inline-fields">
            <div class="inline-field">
              <label>Nazwa
                <input v-model="editMaterialType.name" placeholder="Nazwa" />
              </label>
            </div>
            <div class="actions">
              <button @click="updateItem">Zapisz</button>
              <button class="ghost" @click="editMaterialType.id = null">Anuluj</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
