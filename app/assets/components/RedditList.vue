<template>
  <div class="p-5">
    <div class="max-w-screen-lg mx-auto px-4">
      <h1 class="text-3xl font-bold mb-6 text-gray-800 text-center">Reddit Posts</h1>

      <SearchBox
        class="mb-6"
        v-model="queryInput"
        :suggestions="suggestions"
        :show-suggestions="showSuggestions"
        @input-change="onInputChange"
        @select="selectSuggestion"
        @submit="applySearchFromInput"
      />

      <div v-if="posts.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <PostCard
          v-for="post in posts"
          :key="post.fullname"
          :post="post"
          :asset-path="assetPath"
          :hover-id="hoverId"
          @update:hover-id="hoverId = $event"
          @open-video="openModal($event)"
          :register-video-ref="setVideoRef"
        />
      </div>

      <div v-else class="text-center text-gray-500">No posts found in OpenSearch.</div>

      <div v-if="totalPages > 1" class="mt-6 flex items-center justify-center gap-1 select-none">
        <button
          class="px-3 py-1 rounded border text-sm disabled:opacity-40"
          :disabled="currentPage === 1"
          @click="goToPage(currentPage - 1)"
        >Prev</button>

        <template v-for="(p, idx) in pagesToShow" :key="idx">
          <span v-if="p === '…'" class="px-2 text-gray-500">…</span>
          <button
            v-else
            class="px-3 py-1 rounded border text-sm"
            :class="p === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700'"
            @click="goToPage(p)"
          >{{ p }}</button>
        </template>

        <button
          class="px-3 py-1 rounded border text-sm disabled:opacity-40"
          :disabled="currentPage === totalPages"
          @click="goToPage(currentPage + 1)"
        >Next</button>
      </div>
    </div>

    <VideoModal
      :visible="modalVisible"
      :src="modalSrc"
      :poster="modalPoster"
      @close="closeModal"
    />
  </div>

</template>

<script>
import VideoModal from './VideoModal.vue'
import SearchBox from './SearchBox.vue'
import PostCard from './PostCard.vue'

export default {
  components: {
    VideoModal,
    SearchBox,
    PostCard
  },
  props: {
    initialPosts: {
      type: Array,
      default: () => []
    },
    initialTotal: {
      type: Number,
      default: 0
    },
    initialTotalPages: {
      type: Number,
      default: 1
    },
    initialPage: {
      type: Number,
      default: 1
    },
    assetPath: {
      type: String,
      default: '/'
    }
  },
  computed: {
    pagesToShow() {
      const total = this.totalPages || 1;
      const current = this.currentPage || 1;
      const pages = [];
      if (total <= 7) {
        for (let i = 1; i <= total; i++) pages.push(i);
        return pages;
      }
      const add = (p) => pages.push(p);
      add(1);
      let start = Math.max(2, current - 1);
      let end = Math.min(total - 1, current + 1);
      if (start > 2) add('…');
      for (let i = start; i <= end; i++) add(i);
      if (end < total - 1) add('…');
      add(total);
      return pages;
    }
  },
  data() {
    return {
      posts: this.initialPosts,
      currentPage: this.initialPage,
      pageSize: 16,
      totalPages: this.initialTotalPages,
      total: this.initialTotal,
      hoverId: null,
      hoverTimer: null,
      _videoRefs: new Set(),
      _videoLoaded: new WeakSet(),
      query: '',
      queryInput: '',
      suggestions: [],
      showSuggestions: false,
      _suggestTimer: null,
      loading: false,
      modalVisible: false,
      modalSrc: null,
      modalPoster: null,
    }
  },
  watch: {
    hoverId(newId) {
      if (this.hoverTimer) {
        clearTimeout(this.hoverTimer)
        this.hoverTimer = null
      }
      if (!newId) {
        this._videoRefs.forEach(v => v.pause && v.pause())
        return
      }
      this.hoverTimer = setTimeout(() => {
        const el = this._findVideoByPost(newId)
        if (el) {
          this._ensureVideoLoaded(el)
          el.play && el.play()
        }
      }, 120)
    }
  },
  methods: {
    setVideoRef(el) {
      if (!el) return
      const container = el.closest('[data-fullname]')
      if (container) {
        el.setAttribute('data-for', container.getAttribute('data-fullname'))
      }
      this._videoRefs.add(el)
    },
    _findVideoByPost(fullname) {
      let found = null
      this._videoRefs.forEach(v => {
        if (v.getAttribute('data-for') === fullname) found = v
      })
      return found
    },
    _ensureVideoLoaded(videoEl) {
      if (this._videoLoaded.has(videoEl)) return
      const src = videoEl.getAttribute('data-src')
      if (src) {
        videoEl.src = src
        this._videoLoaded.add(videoEl)
      }
    },
    async fetchPosts(page = 1) {
      this.loading = true;
      try {
        const params = new URLSearchParams();
        if (this.query) params.set('q', this.query);
        params.set('page', String(page));
        params.set('size', String(this.pageSize));
        const res = await fetch(`/api/posts?${params.toString()}`);
        const json = await res.json();
        this.posts = Array.isArray(json.items) ? json.items : [];
        this.total = json.total || 0;
        this.totalPages = json.totalPages || 1;
        this.currentPage = json.page || page;
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error('Failed to fetch posts', e);
        this.posts = [];
        this.total = 0;
        this.totalPages = 1;
      } finally {
        this.loading = false;
      }
    },
    onInputChange() {
      this.showSuggestions = true;
      if (this._suggestTimer) clearTimeout(this._suggestTimer);
      const q = this.queryInput.trim();
      if (!q) {
        this.suggestions = [];
        return;
      }
      this._suggestTimer = setTimeout(async () => {
        try {
          const p = new URLSearchParams({ q, size: String(8) });
          const res = await fetch(`/api/suggest?${p.toString()}`);
          const json = await res.json();
          this.suggestions = Array.isArray(json.suggestions) ? json.suggestions : [];
        } catch (e) {
          // eslint-disable-next-line no-console
          console.error('Failed to fetch suggestions', e);
        }
      }, 300);
    },
    selectSuggestion(text) {
      this.queryInput = text;
      this.applySearchFromInput();
      this.showSuggestions = false;
    },
    applySearchFromInput() {
      const picked = (this.suggestions && this.suggestions.length > 0) ? this.suggestions[0] : this.queryInput;
      this.query = String(picked || '').trim();
      this.currentPage = 1;
      this.pushPageToUrl(1);
      this.fetchPosts(1);
      this.showSuggestions = false;
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      return date.toLocaleString();
    },
    goToPage(p) {
      if (typeof p !== 'number') return;
      if (p < 1 || p > this.totalPages) return;
      this.currentPage = p;
      this.pushPageToUrl(p);
      this.fetchPosts(p);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    pushPageToUrl(p) {
      const path = `/page/${p}`;
      if (window.location.pathname !== path) {
        window.history.pushState({ page: p, q: this.query }, '', path);
      }
    },
    handlePopState() {
      const p = this.readPageFromPath() || 1;
      this.currentPage = p;
      this.fetchPosts(p);
    },
    readPageFromPath() {
      const m = window.location.pathname.match(/^\/page\/(\d+)$/);
      return m ? Math.max(1, parseInt(m[1], 10) || 1) : 1;
    },
    openModal(payload) {
      if (!payload) return;
      this.modalSrc = payload.src || null;
      this.modalPoster = payload.poster || null;
      this.modalVisible = !!this.modalSrc;
    },
    closeModal() {
      this.modalVisible = false;
      this.modalSrc = null;
      this.modalPoster = null;
    }
  },
  mounted() {
    window.addEventListener('popstate', this.handlePopState);
    const initialPage = this.readPageFromPath();
    // If we have data from server for current URL, do not fetch again
    if (this.posts.length > 0 && initialPage === this.currentPage && !this.query) {
      // already set in data()
    } else {
      this.fetchPosts(initialPage);
    }
  },
  beforeUnmount() {
    window.removeEventListener('popstate', this.handlePopState);
  }
}
</script>

<style>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
