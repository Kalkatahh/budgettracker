<template>
  <div>
    <h1>Upload Receipt</h1>
    <form @submit.prevent="uploadFile">
      <input type="file" @change="handleFile" accept="*" />
      <button type="submit" :disabled="!file">Upload</button>
    </form>
    <p v-if="message">{{ message }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import axios from "axios";

const file = ref<File | null>(null);
const message = ref<string>("");

// Store the CSRF token
const csrfToken = ref<string | null>(null);

const handleFile = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files && target.files[0]) {
    file.value = target.files[0];
  }
};

// Fetch the CSRF token when the component is mounted
onMounted(() => {
  const metaTag = document.querySelector('meta[name="csrf-token"]');
  if (metaTag) {
    csrfToken.value = metaTag.getAttribute("content");
    console.log("CSRF Token:", csrfToken.value); // Debugging log
  } else {
    console.error("CSRF meta tag not found.");
  }
});

const uploadFile = async () => {
  if (!file.value) return;

  const formData = new FormData();
  formData.append("file", file.value);

  try {
    if (!csrfToken.value) {
      throw new Error("CSRF token not found.");
    }

    const response = await axios.post("http://localhost:8000/api/upload", formData, {
      headers: {
        "X-CSRF-TOKEN": csrfToken.value, // Include CSRF token in headers
        "Content-Type": "multipart/form-data",
      },
      withCredentials: true, // Ensure cookies are sent with the request
    });

    message.value = response.data.message;
  } catch (error) {
    console.error(error);
    message.value = "An error occurred while uploading.";
  }
};
</script>

<style scoped>
h1 {
  font-size: 24px;
  margin-bottom: 20px;
}
form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-width: 300px;
}
</style>
