import axios from 'axios';
import { router } from '@/router';

const service = axios.create({ baseURL: '', timeout: 30000 });

// 轻量 toast（H5 不引入 UI 框架，保持首屏体积）
export function toast(msg) {
  let el = document.getElementById('mks-toast');
  if (!el) {
    el = document.createElement('div');
    el.id = 'mks-toast';
    document.body.appendChild(el);
  }
  el.textContent = msg;
  el.className = 'mks-toast show';
  clearTimeout(el._t);
  el._t = setTimeout(() => { el.className = 'mks-toast'; }, 2200);
}

service.interceptors.request.use((config) => {
  const token = localStorage.getItem('mks_member_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

service.interceptors.response.use(
  (response) => {
    const res = response.data;
    if (res && typeof res === 'object' && 'code' in res) {
      if (res.code === 0) return res;
      toast(res.msg || '操作失败');
      return Promise.reject(new Error(res.msg || 'Error'));
    }
    return res;
  },
  (error) => {
    const status = error.response?.status;
    if (status === 401) {
      localStorage.removeItem('mks_member_token');
      toast('登录已失效，请重新登录');
      router.push('/login');
    } else {
      toast(error.response?.data?.msg || error.message || '网络错误');
    }
    return Promise.reject(error);
  }
);

export function mksRequest(path, method = 'GET', params = {}, data = undefined) {
  const config = { url: '/index.php', method, params: { ...params, s: path } };
  if (data !== undefined) {
    config.data = data;
    config.headers = { 'Content-Type': 'application/json' };
  }
  return service.request(config);
}

export default service;
