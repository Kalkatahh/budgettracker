<!-- Handles UI and basic file handling -->
<template>
  <div class="upload-container">
    <input 
      type="file"
      ref="fileInput"
      accept=".pdf,.jpg,.jpeg,.png"
      @change="handleFileSelect"
    >
    <button @click="emitUpload" :disabled="!file">
      Upload File
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  maxSize: {
    type: Number,
    default: 10 * 1024 * 1024 // 10MB
  }
});

const emit = defineEmits(['upload']);

const file = ref(null);

const handleFileSelect = (e) => {
  const selectedFile = e.target.files[0];
  if (!selectedFile) return;

  if (selectedFile.size > props.maxSize) {
    alert(`File exceeds ${props.maxSize/1024/1024}MB limit`);
    return;
  }

  file.value = selectedFile;
};

const emitUpload = () => {
  if (file.value) {
    emit('upload', file.value);
    file.value = null;
  }
};
</script>
