<script setup>
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import { login, state } from '../stores/appStore'

const router = useRouter()

const form = reactive({
  email: '',
  password: '',
})

const submit = async () => {
  try {
    await login(form.email, form.password)
    router.push({ name: 'upload' })
  } catch (error) {
    // errors handled via state.authError
  }
}
</script>

<template>
  <main class="panel">
    <h2>Zaloguj się</h2>
    <form class="form-grid" @submit.prevent="submit">
      <label>
        <span>Email</span>
        <input v-model="form.email" type="email" required placeholder="you@example.com" />
      </label>
      <label>
        <span>Hasło</span>
        <input v-model="form.password" type="password" required placeholder="••••••••" />
      </label>
      <button type="submit" :disabled="state.loading">Zaloguj</button>
      <p v-if="state.authError" class="error">{{ state.authError }}</p>
    </form>
  </main>
</template>
