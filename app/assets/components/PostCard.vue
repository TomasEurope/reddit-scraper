<template>
  <div
    class="group relative bg-white rounded shadow hover:shadow-md overflow-hidden"
    :data-fullname="post.fullname"
    @mouseenter="$emit('update:hover-id', post.fullname)"
    @mouseleave="$emit('update:hover-id', null)"
    @click="onOpen"
  >
    <div class="aspect-square w-full overflow-hidden bg-gray-100 relative" style="aspect-ratio: 1/1">
      <img
        v-if="thumbUrl"
        :src="thumbUrl"
        :alt="post.title"
        class="w-full h-full object-cover transition-opacity duration-200"
        :class="{ 'opacity-0': hoverId === post.fullname && !!videoUrl }"
        @error="onImgError"
      />
      <video
        v-if="videoUrl"
        :ref="el => registerVideoRef && registerVideoRef(el)"
        :data-src="videoUrl"
        :poster="thumbUrl"
        preload="none"
        class="absolute inset-0 w-full h-full object-cover transition-opacity duration-200"
        :class="{ 'opacity-0': hoverId !== post.fullname }"
        muted
        loop
        playsinline
      ></video>

      <div
        class="absolute inset-x-0 bottom-0 p-3 text-white transition-opacity duration-200"
        :class="{ 'opacity-0': hoverId === post.fullname && !!videoUrl, 'opacity-100': hoverId !== post.fullname || !videoUrl }"
      >
        <div class="pointer-events-none bg-gradient-to-t from-black/60 via-black/30 to-transparent absolute inset-x-0 bottom-0 top-0"></div>
        <div class="relative">
          <div class="text-sm font-semibold line-clamp-2 drop-shadow" style="text-shadow: 0 1px black;">{{ post.title }}</div>
          <div class="mt-1 text-[11px] flex items-center justify-between opacity-90" style="text-shadow: 0 1px black;">
            <span>Ups: {{ post.ups }}</span>
            <span>{{ post.upvote_ratio }}%</span>
          </div>
          <div class="mt-0.5 text-[10px] opacity-80" style="text-shadow: 0 1px black;">{{ formatDate(post.created_at_utc) }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    post: { type: Object, required: true },
    assetPath: { type: String, default: '/' },
    hoverId: { type: String, default: null },
    registerVideoRef: { type: Function, default: null }
  },
  emits: ['update:hover-id', 'open-video'],
  computed: {
    thumbUrl() {
      const path = this.post.local_thumbnail || this.post.localThumbnail
      if (!path) return null
      return this.joinPath(this.assetPath, path)
    },
    videoUrl() {
      const path = this.post.local_mp4 || this.post.localMp4
      if (!path) return null
      return this.joinPath(this.assetPath, path)
    }
  },
  methods: {
    joinPath(base, path) {
      if (!base) return path
      if (!path) return base
      const b = base.endsWith('/') ? base : base + '/'
      const p = path.startsWith('/') ? path.substring(1) : path
      return b + p
    },
    formatDate(dateStr) {
      if (!dateStr) return ''
      const date = new Date(dateStr)
      return date.toLocaleString()
    },
    onImgError(e) {
      console.error(`Failed to load image: ${this.thumbUrl}`, {
        post: this.post.fullname,
        assetPath: this.assetPath
      });
    },
    onOpen() {
      if (!this.videoUrl) return
      this.$emit('open-video', {
        src: this.videoUrl,
        poster: this.thumbUrl
      })
    }
  }
}
</script>

<style scoped>
</style>
