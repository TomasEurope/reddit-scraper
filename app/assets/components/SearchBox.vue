<template>
  <div class="relative">
    <input
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value); $emit('input-change')"
      @keydown.enter.prevent="$emit('submit')"
      type="text"
      class="w-full border rounded px-4 py-2 shadow-sm focus:outline-none focus:ring"
      placeholder="Hledat…"
    />
    <div
      v-if="showSuggestions && suggestions && suggestions.length > 0"
      class="absolute z-10 mt-1 w-full bg-white border rounded shadow"
    >
      <ul>
        <li
          v-for="(s, i) in suggestions"
          :key="i"
          class="px-4 py-2 hover:bg-gray-100 cursor-pointer truncate"
          @click="$emit('select', s)"
          :title="s"
        >{{ s }}</li>
      </ul>
    </div>
  </div>
</template>

<script>
export default {
  emits: ['update:modelValue', 'input-change', 'submit', 'select'],
  props: {
    modelValue: { type: String, default: '' },
    suggestions: { type: Array, default: () => [] },
    showSuggestions: { type: Boolean, default: false }
  }
}
</script>

<style scoped>
</style>
