<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import api from '../services/api'
import { loadMaterialTypes, loadProjects, state } from '../stores/appStore'

const selectedProjectId = ref(null)
const selectedFile = ref(null)
const uploadToken = ref('')
const unknownMaterials = ref([])
const materialAssignments = reactive({})
const downloadUrl = ref('')
const fileLinks = ref([])
const statusMessage = ref('')
const loading = ref(false)
const remarks = ref([])

const resetUploadState = () => {
  uploadToken.value = ''
  unknownMaterials.value = []
  Object.keys(materialAssignments).forEach((key) => delete materialAssignments[key])
  downloadUrl.value = ''
  fileLinks.value = []
  remarks.value = []
  statusMessage.value = ''
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
    remarks.value = data.remarks ?? []
    data.unknown_materials.forEach((item) => {
      materialAssignments[item] = data.material_types[0]?.id ?? null
    })
    state.materialTypes = data.material_types
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
    fileLinks.value = data.file_urls ?? []
    remarks.value = data.remarks ?? remarks.value
    statusMessage.value = 'Pliki wygenerowane. Poniżej znajdziesz link do ZIP.'
  } catch (error) {
    statusMessage.value = error.response?.data?.message ?? 'Generowanie nie powiodło się.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (!state.projects.length) await loadProjects()
  if (!state.materialTypes.length) await loadMaterialTypes()
  if (state.projects.length) {
    selectedProjectId.value = state.projects[0].id
  }
})

watch(
  () => state.projects,
  (projects) => {
    if (!selectedProjectId.value && projects.length) {
      selectedProjectId.value = projects[0].id
    }
  },
  { deep: false },
)
</script>

<template>
  <section class="card card-wide">
    <div class="card-head">
      <div>
        <p class="eyebrow">Generowanie</p>
        <h3>Upload i przypisanie materiałów</h3>
      </div>
      <!--div class="tag">{{ state.projects.length }} projektów</div-->
    </div>
    <div class="split">
      <div class="stack">
        <label>
          <span>Projekt</span>
          <select v-model.number="selectedProjectId">
            <option :value="null" disabled>Wybierz projekt</option>
            <option v-for="project in state.projects" :key="project.id" :value="project.id">
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
            Generuj
          </button>
        </div>
        <p v-if="statusMessage" class="hint">{{ statusMessage }}</p>
        <p v-if="downloadUrl" class="success">
          Pliki gotowe: <a :href="downloadUrl" target="_blank" rel="noopener">pobierz ZIP</a>
        </p>
        <div v-if="remarks.length" class="remarks">
          <p>Uwagi:</p>
          <ul>
            <li v-for="note in remarks" :key="note">{{ note }}</li>
          </ul>
        </div>
        <div v-if="fileLinks.length" class="file-links">
          <p>Arkusze per materiał:</p>
          <ul>
            <li v-for="file in fileLinks" :key="file.name">
              <a :href="file.url" target="_blank" rel="noopener">{{ file.name }}</a>
            </li>
          </ul>
        </div>
      </div>
      <div class="stack review">
        <h4>Nieznane materiały</h4>
        <p v-if="unknownMaterials.length === 0">Brak pozycji do uzupełnienia.</p>
        <div v-else class="unknown-list">
          <div v-for="name in unknownMaterials" :key="name" class="unknown-item">
            <div class="pill">{{ name }}</div>
            <select v-model.number="materialAssignments[name]">
              <option :value="null" disabled>Wybierz typ</option>
              <option v-for="type in state.materialTypes" :key="type.id" :value="type.id">
                {{ type.name }}
              </option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
