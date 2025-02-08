<script setup>
import { ref } from 'vue';
import FileUploadComponent from './FileUploadComponent.vue';
import useFileUpload from '@/composables/useFileUpload';

const { uploadFile, isLoading: isUploading, progress: uploadProgress, error: uploadError } = useFileUpload();

const handleUpload = async (file) => {
  try {
    const result = await uploadFile(file);
    console.log('Upload successful:', result);
    // Add logic to update file list
  } catch {
    // Error handled in composable
  }
};
</script>

<template>
  <div class="home-component">
    <h1>File Upload</h1>
    <FileUploadComponent 
      @upload="handleUpload"
      :max-size="15 * 1024 * 1024"
    />
    
    <div v-if="uploadError" class="error">
      {{ uploadError }}
    </div>
    
    <div v-if="isUploading">
      Upload Progress: {{ uploadProgress }}%
    </div>
  </div>
</template>

<style scoped>
.home-component {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem;
}
</style>
