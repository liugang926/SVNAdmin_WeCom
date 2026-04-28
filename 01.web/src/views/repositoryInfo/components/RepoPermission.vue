<template>
  <ModalRepPri
    :propCurrentRepName="currentRepName"
    :propCurrentRepPath="currentRepPath"
    :propModalRepPri="visible"
    :propChangeParentModalVisible="handleClose"
    :propChangeParentCurrentRepPath="handlePathChange"
    :propSvnnUserPriPathId="svnnUserPriPathId"
  />
</template>

<script>
import ModalRepPri from "@/components/modalRepPri.vue";

export default {
  name: "RepoPermission",
  components: {
    ModalRepPri,
  },
  props: {
    visible: {
      type: Boolean,
      default: false,
    },
    currentRepName: {
      type: String,
      default: "",
    },
    currentRepPath: {
      type: String,
      default: "/",
    },
    svnnUserPriPathId: {
      type: [String, Number],
      default: -1,
    },
  },
  methods: {
    handleClose() {
      this.$emit("close");
    },
    handlePathChange(path) {
      this.$emit("path-change", path);
    },
  },
};
</script>
