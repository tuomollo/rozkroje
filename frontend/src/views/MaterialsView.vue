<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import {
  createMaterial,
  deleteMaterial,
  loadMaterialTypes,
  loadMaterials,
  state,
  updateMaterial,
} from '../stores/appStore'

const search = ref('')
const currentPage = ref(1)
const perPage = ref(10)
const statusMessage = ref('')
const showCreate = ref(false)
const newMaterial = reactive({ name: '', material_type_id: null, has_grain: false })
const editMaterial = reactive({ id: null, name: '', material_type_id: null, has_grain: false })

const fetchData = async () => {
  await loadMaterials(currentPage.value, search.value, perPage.value)
}

onMounted(async () => {
  if (!state.materialTypes.length) {
    await loadMaterialTypes()
  }
  await fetchData()
})

watch(search, () => {
  currentPage.value = 1
  fetchData()
})

const submitNew = async () => {
  if (!newMaterial.name || !newMaterial.material_type_id) return
  statusMessage.value = ''
  try {
    await createMaterial({
      name: newMaterial.name,
      material_type_id: newMaterial.material_type_id,
      has_grain: newMaterial.has_grain,
    })
    newMaterial.name = ''
    newMaterial.material_type_id = null
    newMaterial.has_grain = false
    showCreate.value = false
    statusMessage.value = 'Dodano materiał.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Nie udało się dodać materiału.'
  }
}

const startEdit = (item) => {
  editMaterial.id = item.id
  editMaterial.name = item.name
  editMaterial.material_type_id = item.material_type_id
  editMaterial.has_grain = !!item.has_grain
}

const submitEdit = async () => {
  if (!editMaterial.id) return
  statusMessage.value = ''
  try {
    await updateMaterial(editMaterial.id, {
      name: editMaterial.name,
      material_type_id: editMaterial.material_type_id,
      has_grain: editMaterial.has_grain,
    })
    statusMessage.value = 'Zapisano zmiany.'
    editMaterial.id = null
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Aktualizacja nie powiodła się.'
  }
}

const removeItem = async (id) => {
  if (!window.confirm('Czy jesteś pewien, że chcesz usunąć ten element?')) return
  statusMessage.value = ''
  try {
    await deleteMaterial(id)
    statusMessage.value = 'Usunięto.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Usuwanie nie powiodło się.'
  }
}

const nextPage = () => {
  if (state.materialsMeta.current_page < state.materialsMeta.last_page) {
    currentPage.value += 1
    fetchData()
  }
}

const prevPage = () => {
  if (state.materialsMeta.current_page > 1) {
    currentPage.value -= 1
    fetchData()
  }
}
</script>

<template>
  <section class="card card-wide">
    <div class="card-head">
      <div>
        <p class="eyebrow">Materiały</p>
        <h3>Lista, edycja i wyszukiwanie</h3>
      </div>
      <div class="actions">
        <input v-model="search" placeholder="Szukaj po nazwie..." />
        <button class="ghost" @click="showCreate = !showCreate">{{ showCreate ? 'Schowaj' : '➕' }}</button>
      </div>
    </div>

    <form v-if="showCreate" class="form-grid" @submit.prevent="submitNew">
      <input v-model="newMaterial.name" placeholder="Nazwa materiału" required />
      <select v-model.number="newMaterial.material_type_id" required>
        <option :value="null" disabled>Typ materiału</option>
        <option v-for="type in state.materialTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
      </select>
      <label class="checkbox">
        <input type="checkbox" v-model="newMaterial.has_grain" />
        <span>Ma usłojenie</span>
      </label>
      <button type="submit">Nowy</button>
    </form>

    <p v-if="statusMessage" class="hint">{{ statusMessage }}</p>

    <div class="list">
      <div v-for="item in state.materials" :key="item.id" class="row">
        <div class="row-main">
          <strong>{{ item.name }}</strong>
          <span class="muted">
            {{ item.type?.name || '—' }} • {{ item.has_grain ? 'Usłojenie' : 'Brak usłojenia' }}
          </span>
        </div>
        <div class="row-actions">
          <button class="ghost" @click="startEdit(item)">🖉</button>
          <button class="ghost danger" @click="removeItem(item.id)">🗑️</button>
        </div>
        <div v-if="editMaterial.id === item.id" class="inline-edit">
          <h4>Edytuj materiał</h4>
          <div class="inline-fields">
            <div class="inline-field">
              <label>Nazwa
                <input v-model="editMaterial.name" placeholder="Nazwa" />
              </label>
            </div>
            <div class="inline-field">
              <label>Typ
                <select v-model.number="editMaterial.material_type_id">
                  <option v-for="type in state.materialTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                </select>
              </label>
            </div>
            <div class="actions">
              <button @click="submitEdit">Zapisz</button>
              <button class="ghost" @click="editMaterial.id = null">Anuluj</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="pagination" >
      <button class="ghost" @click="prevPage" :disabled="state.materialsMeta.current_page === 1">Poprzednia</button>
      <span>Strona {{ state.materialsMeta.current_page }} / {{ state.materialsMeta.last_page }}</span>
      <button class="ghost" @click="nextPage" :disabled="state.materialsMeta.current_page === state.materialsMeta.last_page">
        Następna
      </button>
    </div>

  </section>
</template>
