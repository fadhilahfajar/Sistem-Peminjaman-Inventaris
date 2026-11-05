<script setup>
defineProps({
  id: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    required: true,
  },
  wajib: {
    type: Boolean,
    default: false,
  },
  galat: {
    type: String,
    default: '',
  },
  bantuan: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <div class="flex flex-col gap-1.5 w-full">
    <label
      v-if="label"
      :for="id || undefined"
      class="text-sm font-medium text-slate-800"
    >
      {{ label }}
      <span v-if="wajib" class="text-red-600 font-bold" aria-hidden="true">*</span>
    </label>

    <div>
      <slot :id="id" :aria-describedby="galat ? `${id}-error` : bantuan ? `${id}-help` : undefined" />
    </div>

    <p
      v-if="bantuan && !galat"
      :id="id ? `${id}-help` : undefined"
      class="text-xs text-slate-500"
    >
      {{ bantuan }}
    </p>

    <p
      v-if="galat"
      :id="id ? `${id}-error` : undefined"
      class="text-xs font-medium text-red-600"
      role="alert"
    >
      {{ galat }}
    </p>
  </div>
</template>
