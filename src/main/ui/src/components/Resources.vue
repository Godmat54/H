<script setup>
import { ref } from "vue";

const ok = ref(false);
</script>

<template>
  <div class="container">
    <h1>Resources</h1>
    <h4>
      For a guide and recipes on how to configure / customize an Equo App, check
      out the
      <a
        href="https://docs.equo.dev/"
        target="_blank"
        rel="noreferrer noredirect"
      >
        SDK documentation
      </a>
      .
    </h4>
    <div class="boxes">
      <a
        href="https://docs.equo.dev/sdk/1.0.x/getting-started/creating.html"
        target="_blank"
        rel="noreferrer noredirect"
      >
        Development guide
      </a>
      <a
        href="https://docs.equo.dev/sdk/1.0.x/developing/hot-reload.html"
        target="_blank"
        rel="noreferrer noredirect"
      >
        Web UI frameworks Development
      </a>
      <a
        href="https://docs.equo.dev/sdk/1.0.x/index-api-javascript.html"
        target="_blank"
        rel="noreferrer noredirect"
      >
        Javascript API documentation
      </a>
      <a
        href="https://docs.equo.dev/sdk/1.0.x/index-api-java.html"
        target="_blank"
        rel="noreferrer noredirect"
      >
        Java API documentation
      </a>
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
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
}

h4 {
  font-size: 20px;
  font-weight: 300;
}

a {
  color: #3884ff;
}

.boxes a {
  width: 200px;
  height: 200px;
  background-color: #1b1d21;
  padding: 20px;
  border-radius: 10px;
  margin: 10px 0;

  font-size: 24px;
  font-weight: 700;
}
</style>
