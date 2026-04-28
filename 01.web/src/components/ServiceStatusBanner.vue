<template>
  <div v-if="visibleItems.length" class="service-status-banner">
    <Alert
      v-for="item in visibleItems"
      :key="item.key || item.message"
      :type="item.type || 'warning'"
      show-icon
      class="service-status-banner__item"
    >
      {{ item.message }}
      <template v-if="item.desc" slot="desc">{{ item.desc }}</template>
    </Alert>
  </div>
</template>

<script>
export default {
  name: "ServiceStatusBanner",
  props: {
    items: {
      type: Array,
      default: function () {
        return [];
      },
    },
  },
  computed: {
    visibleItems() {
      return (this.items || []).filter(function (item) {
        return item && item.visible !== false && item.message;
      });
    },
  },
};
</script>

<style lang="less" scoped>
.service-status-banner {
  margin-bottom: 12px;
}

.service-status-banner__item {
  margin-bottom: 12px;
}

.service-status-banner__item:last-child {
  margin-bottom: 0;
}
</style>
