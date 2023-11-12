<script setup>
import First from "./First.vue";
import Second from "./Second.vue";
import Third from "./Third.vue";
</script>

<template>
  <div class="container">
    <h1>Examples</h1>
    <div class="boxes">
      <First />
      <Second />
      <Third />
    </div>
  </div>
</template>

<style scoped>
.container {
  display: flex;
  flex-direction: column;
  margin-top: 20px;
  margin-left: 30px;
}

.boxes {
  margin-top: 100px;
  display: grid;
  grid-template-columns: repeat(auto-fill, 700px);
}

.boxes > div {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  width: 630px;
  height: 600px;
  background-color: #1b1d21;
  padding: 0px 20px 20px;
  border-radius: 10px;
  margin-bottom: 30px;
}

.boxes:deep(.codes) {
  flex-direction: row;
  margin-top: 20px;
}

.boxes:deep(.codes > div) {
  background: #2b2e38;
  border-radius: 2%;
}

.boxes:deep(pre) {
  padding: 20px;
}

.boxes.boxes:deep(.try-it) {
  display: grid;
  grid-template-columns: 100px 20px 1fr;
  align-items: center;
}
</style>
