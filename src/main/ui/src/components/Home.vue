<script setup>
import { ref } from "vue";

const ok = ref(false);
</script>

<template>
  <div class="container">
    <Transition name="fade" appear @after-appear="ok = true">
      <img class="equo-logo" src="../assets/equo-logo.png" width="200" />
    </Transition>
    <Transition name="fade">
      <h1 v-show="ok">Welcome to your first Equo App!</h1>
    </Transition>
  </div>
</template>

<style scoped>
.container {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.equo-logo {
  margin-top: 35vh;
}
</style>
