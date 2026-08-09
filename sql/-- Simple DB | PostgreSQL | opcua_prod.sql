-- Simple DB | PostgreSQL | opcua_prod

SELECT * 
FROM pesadas_individuales
where ref_id like '%-2'
order by id desc
;

select * 
from pesadora_lineas
order by id desc
;