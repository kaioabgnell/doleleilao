/** Menu do painel recolhido (D-45): guardado no navegador e aplicado em <html data-sidebar> antes da pintura. */
export const SIDEBAR_STORAGE_KEY = "dl-painel-menu";

export const restoreSidebarScript = `try{if(localStorage.getItem(${JSON.stringify(SIDEBAR_STORAGE_KEY)})==="collapsed")document.documentElement.dataset.sidebar="collapsed"}catch(e){}`;
