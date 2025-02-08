<script setup lang="ts">
import { RouterView } from 'vue-router';
</script>

<template>
  <header>
    <img alt="Vue logo" class="logo" src="@/assets/logo.svg" width="125" height="125" />
    
    <nav>
      <router-link to="/">Home</router-link>
    </nav>
  </header>

  <RouterView />
</template>

