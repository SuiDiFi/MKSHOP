import axios from 'axios';
import { ElMessage } from 'element-plus';
import { router } from '@/router';

const API_BASE = import.meta.env.VITE_API_BASE || '';

const service = axios.create({
  baseURL: API_BASE,
  timeout: 30000,
});

service.interceptors.request.use((config) => {
  const token = localStorage.getItem('mks_member_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

service.interceptors.response.use(
  (response) => {
    const res = response.data;
    if (res && typeof res === 'object' && 'code' in res) {
      if (res.code === 0) {
        return res;
      }
      ElMessage.error(res.msg || '操作失败');
      return Promise.reject(new Error(res.msg || 'Error'));
    }
    return res;
  },
  (error) => {
    const status = error.response?.status;
    const data = error.response?.data;
    if (status === 401) {
      localStorage.removeItem('mks_member_token');
      ElMessage.error('登录已失效，请重新登录');
      router.push('/login');
    } else {
      ElMessage.error(data?.msg || data?.message || error.message || '网络错误');
    }
    return Promise.reject(error);
  }
);

/**
 * 统一请求入口（think 多应用兼容模式：s= 参数携带路由）
 */
export function mksRequest(path, method = 'GET', params = {}, data = undefined) {
  const config = { url: '/index.php', method, params: { ...params, s: path } };
  if (data !== undefined) {
    config.data = data;
    config.headers = { 'Content-Type': 'application/json' };
  }
  return service.request(config);
}

export default service;
