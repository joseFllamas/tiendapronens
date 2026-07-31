var url_prefix_main = location.protocol + '//' + window.location.hostname + window.location.pathname.replace('index.php', '');

//Funcionalidad para tratar fechas
let co_fecha = new Date();
let co_mes = co_fecha.getMonth() + 1;
let co_dia = co_fecha.getDate();
let co_ano = co_fecha.getFullYear();
if (co_dia < 10) co_dia = '0' + co_dia;
if (co_mes < 10) co_mes = '0' + co_mes;
