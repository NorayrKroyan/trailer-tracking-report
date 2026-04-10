import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import 'datatables.net-dt/css/dataTables.dataTables.css'
import 'datatables.net-responsive-dt/css/responsive.dataTables.css'
import 'datatables.net-fixedheader-dt/css/fixedHeader.dataTables.css'
import './style.css'

createApp(App).use(router).mount('#app')