import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { memberInfo } from '@/api';

export const useUserStore = defineStore('user', () => {
  const token = ref(localStorage.getItem('mks_member_token') || '');
  const userinfo = ref(null);

  const isLogin = computed(() => !!token.value);

  function setToken(t) {
    token.value = t || '';
    if (t) {
      localStorage.setItem('mks_member_token', t);
    } else {
      localStorage.removeItem('mks_member_token');
      userinfo.value = null;
    }
  }

  async function fetchInfo() {
    const res = await memberInfo();
    userinfo.value = res.data?.userinfo || null;
    return userinfo.value;
  }

  function logout() {
    setToken('');
  }

  return { token, userinfo, isLogin, setToken, fetchInfo, logout };
});
