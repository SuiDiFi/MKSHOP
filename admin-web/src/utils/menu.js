/**
 * 菜单树工具（后端 admin_router 下发 → 前端路径/面包屑/分栏列）
 * 规则与 router/index.js 的 registerDynamicRoutes 保持一致：
 *   节点 path 以 / 开头则为绝对路径，否则拼在父路径之后
 */

/** 拼出节点绝对路径 */
export function joinPath(parentPath, nodePath) {
  const p = String(nodePath || '');
  if (p.startsWith('/')) return p;
  return `${parentPath || ''}/${p}`.replace(/\/{2,}/g, '/').replace(/\/$/, '') || parentPath || '/';
}

/**
 * 展平菜单树，附带绝对路径与祖先链
 * @returns Array<{node, fullPath, parents: Array<{title,icon,path}>}>
 */
export function flattenMenus(menus, parentPath = '', parents = [], out = []) {
  for (const node of menus || []) {
    if (Number(node.auth_type) === 2) continue; // 接口权限点不参与导航
    const fullPath = joinPath(parentPath, node.path);
    // 容器节点（component=Layout）本身不是页面，不能当面包屑链接
    // （继承的菜单树里「商品/用户/订单」渠道 path 与首页下统计页 path 同名，链接会跳错页）
    const chain = [...parents, { title: node.title, icon: node.icon, path: fullPath, layout: node.component === 'Layout' }];
    out.push({ node, fullPath, parents: chain });
    if (node.children?.length) flattenMenus(node.children, fullPath, chain, out);
  }
  return out;
}

/** 可见菜单（过滤 hidden 与无 component 的容器节点） */
export function visibleMenus(menus) {
  return (menus || []).filter((m) => !Number(m.hidden) && m.path);
}

/**
 * 参与导航的一级渠道：必须有可见子项或自身就是页面
 * 修正点：顶级「首页」path='/' 不能当垃圾过滤掉，否则横向/纵向布局会丢失首页入口
 */
export function navMenus(menus) {
  return visibleMenus(menus).filter((m) => {
    if (m.component && m.component !== 'Layout') return true;
    return visibleMenus(m.children || []).length > 0;
  });
}

/**
 * 当前路由的面包屑（对方规范：每项带图标、分隔符 >、可点击）
 * @param {string} routePath 当前绝对路径（不含 query）
 * @param {Array} menus 后端菜单树
 * @param {Map<number,string>} pathMap menuId → 绝对路径（动态路由注册时产出）
 */
export function breadcrumbOf(routePath, menus, pathMap) {
  const flat = flattenMenus(menus);
  // 精确匹配优先，其次取最长前缀匹配（/goods/list/edit/:id → /goods/list）
  // 同 path 多菜单行时取可见行，否则面包屑会用遗留行的旧标题
  let hit = flat
    .filter((f) => f.fullPath === routePath)
    .sort((a, b) => Number(!!a.node.hidden) - Number(!!b.node.hidden))[0];
  if (!hit) {
    const cands = flat
      .filter((f) => routePath.startsWith(f.fullPath + '/'))
      .sort((a, b) => b.fullPath.length - a.fullPath.length);
    hit = cands[0];
  }
  if (!hit) return [];
  const chain = hit.parents.filter((p) => p.title);
  if (hit.node.breadcrumbHidden) return chain.slice(0, 1);
  return chain;
}

/**
 * 取某顶级渠道下的可点菜单项（二级；三级用 divider 分组）
 * @param {object} group 顶级菜单节点
 * @param {string} basePath 该渠道绝对路径
 * @returns {Array<{type:'item'|'divider', title, icon?, path, menuId?}>}
 */
export function groupItems(group, basePath) {
  const items = [];
  for (const c of visibleMenus(group?.children || [])) {
    const cPath = joinPath(basePath, c.path);
    const grand = visibleMenus(c.children || []);
    if (grand.length) {
      items.push({ type: 'divider', title: c.title });
      for (const g3 of grand) {
        items.push({
          type: 'item',
          title: g3.title,
          icon: g3.icon || c.icon,
          path: joinPath(cPath, g3.path),
          menuId: g3.id,
        });
      }
    } else {
      items.push({ type: 'item', title: c.title, icon: c.icon, path: cPath, menuId: c.id });
    }
  }
  return items;
}

/**
 * 分栏布局数据：一级渠道（顶级节点）+ 当前渠道的二级项（含三级分组标题）
 * @returns {{groups: Array, active: Object|null, items: Array}}
 */
export function columnOf(menus, routePath) {
  const groups = navMenus(menus).map((g) => ({
    menuId: g.id,
    title: g.title,
    icon: g.icon,
    path: joinPath('', g.path),
    raw: g,
  }));

  // 选中渠道：取最长前缀匹配（顶级「首页」path='/' 会匹配所有路由，必须按长度择长）
  const hitBase = (p) => (p === '/' ? '/' : p + '/');
  const matched = groups.filter((g) => routePath === g.path || routePath.startsWith(hitBase(g.path)));
  const active = matched.sort((a, b) => b.path.length - a.path.length)[0] || groups[0] || null;

  return { groups, active, items: active ? groupItems(active.raw, active.path) : [] };
}
