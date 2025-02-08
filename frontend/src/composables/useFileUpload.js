const uploadFile = async (file) => {
  const formData = new FormData();
  
  // Ensure we're appending the actual File object
  formData.append('file', file); // file must be a File instance
  
  try {
    const response = await api.post('/upload', formData, {
      // Remove explicit Content-Type header
      onUploadProgress: (progressEvent) => {
        progress.value = Math.round(
          (progressEvent.loaded * 100) / progressEvent.total
        );
      }
    });
    return response.data;
  } catch (err) {
    console.error('Upload error:', err.response?.data);
    error.value = err.response?.data?.message || 'Upload failed';
    throw err;
  } finally {
    isLoading.value = false;
  }
};
