<template>
  <div
    v-if="visible"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 cursor-pointer p-4"
    @click="$emit('close')"
  >
    <video
      class="max-w-full max-h-full cursor-pointer object-contain"
      :poster="poster || null"
      :src="src"
      autoplay
      muted
      loop
      playsinline
    ></video>
  </div>
</template>

<script>
export default {
  props: {
    visible: { type: Boolean, default: false },
    src: { type: String, default: null },
    poster: { type: String, default: null },
  },
  watch: {
    visible(val) {
      if (val) {
        window.addEventListener('keydown', this.onKey)
      } else {
        window.removeEventListener('keydown', this.onKey)
      }
    }
  },
  methods: {
    onKey(e) {
      if (e.key === 'Escape') this.$emit('close')
    }
  },
  mounted() {
    if (this.visible) window.addEventListener('keydown', this.onKey)
  },
  beforeUnmount() {
    window.removeEventListener('keydown', this.onKey)
  }
}
</script>

<style scoped>
</style>
