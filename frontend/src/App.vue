<script setup>
import { onMounted } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { isAdmin, logout, restoreSession, state } from './stores/appStore'

const route = useRoute()
const router = useRouter()

onMounted(() => {
  restoreSession().catch(() => {})
})

const isActive = (name) => route.name === name
const handleLogout = () => {
  logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="app-shell">
    <header class="hero header">
      <div>
        <p class="eyebrow">Skywood</p>
        <h1>Rozkroje</h1>
      </div>
      <div class="user-chip" v-if="state.user">
        <div>
          <div class="chip-name">{{ state.user.name }}</div>
          <div class="chip-role">{{ state.user.is_admin ? 'Administrator' : 'Użytkownik' }}</div>
        </div>
        <button class="ghost" @click="handleLogout">Wyloguj</button>
      </div>
    </header>

    <div class="shell-body" :class="{ 'with-sidebar': state.user }">
      <aside class="sidebar" v-if="state.user">
        <nav class="side-nav">
          <RouterLink :class="{ active: isActive('upload') }" to="/upload">📃 Generowanie</RouterLink>
          <RouterLink :class="{ active: isActive('projects') }" to="/projects">📕 Projekty</RouterLink>
          <RouterLink v-if="isAdmin" :class="{ active: isActive('material-types') }" to="/material-types">
            ⚙ Typy materiałów
          </RouterLink>
          <RouterLink v-if="isAdmin" :class="{ active: isActive('users') }" to="/users">👫 Użytkownicy</RouterLink>
        </nav>
      </aside>

      <main class="content">
        <RouterView />
      </main>
    </div>
  </div>
</template>
