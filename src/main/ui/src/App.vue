<script setup>
import { ref, computed } from "vue";
import Home from "./components/Home.vue";
import Docs from "./components/Docs.vue";
import Resources from "./components/Resources.vue";
import Examples from "./components/Examples/index.vue";

const routes = {
  "/": Home,
  "/examples": Examples,
  "/resources": Resources,
  "/docs": Docs,
};

const currentPath = ref(window.location.hash);

window.addEventListener("hashchange", () => {
  currentPath.value = window.location.hash;
});

const currentView = computed(() => {
  return routes[currentPath.value.slice(1) || "/"] || Home;
});
</script>

<template>
  <div class="layout">
    <nav>
      <a href="#/">
        <img src="./assets/home.png" />
        <p>Start</p>
      </a>
      <a href="#/examples">
        <img src="./assets/box.png" />
        <p>Examples</p>
      </a>
      <a href="#/resources">
        <img src="./assets/book-open.png" />
        <p>Resources</p>
      </a>
      <a href="#/docs">
        <img src="./assets/globe.png" />
        <p>Docs</p>
      </a>
    </nav>
    <main>
      <component :is="currentView" />
    </main>
  </div>
</template>

<style scoped>
.layout {
  display: grid;
  grid-template-columns: 150px 1fr;
  height: 100vh;
}

main {
  margin: 12px 12px 12px 0;
}

nav {
  background-color: #1b1d21;
  margin: 10px;

  display: grid;
  grid-template-rows: repeat(4, 150px);
}

nav a {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  opacity: 0.7;
}

nav a:hover {
  opacity: 1;
  border-radius: 2%;
  margin: 10px;
  transition: 0.3s;
}

p {
  color: #f6f6f6;
  font-weight: 300;
}
</style>

<style>
@font-face {
  font-family: Lato;
  src: url("./assets/Lato/Lato-Bold.ttf");
  font-weight: 700;
}

@font-face {
  font-family: Lato;
  src: url("./assets/Lato/Lato-Regular.ttf");
  font-weight: 400;
}

@font-face {
  font-family: Lato;
  src: url("./assets/Lato/Lato-Light.ttf");
  font-weight: 300;
}

@font-face {
  font-family: Roboto Mono;
  src: url("./assets/Roboto_Mono/RobotoMono-Light.ttf");
}

body {
  padding: 0;
  margin: 0;
  font-family: Lato;
  background: #2b2e38;
  color: #f6f6f6;
}

h1 {
  font-size: 36px;
  font-weight: 700;
  margin: 0;
  letter-spacing: -0.03em;
}

h5 {
  font-size: 24px;
  font-weight: bold;
  line-height: 0;
}

p {
  line-height: 0;
}

a {
  text-decoration: none;
}

code {
  font-family: "Roboto Mono", monospace;
  font-size: 14px;
}

button {
  display: inline-block;
  outline: none;
  cursor: pointer;
  padding: 0 16px;
  background-color: #1b1d21;
  border-radius: 0.25rem;
  border: 2px solid #0070d2;
  color: #3884ff;
  font-size: 14px;
  line-height: 30px;
  font-weight: 400;
  text-align: center;
  height: 40px;
}

button:hover {
  background: rgb(0, 101, 255);
  text-decoration: inherit;
  transition-duration: 0s, 0.15s;
  color: rgb(255, 255, 255);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 1s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
