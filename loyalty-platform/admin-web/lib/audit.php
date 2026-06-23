<?php
// تسجيل كل إجراء حسّاس في admin.audit.
function audit(string $action, ?string $entity = null, ?string $entityId = null, ?array $meta = null): void {
  $a = current_admin();
  q("insert into admin.audit (admin_id, admin_name, action, entity, entity_id, meta)
     values (:aid, :an, :ac, :en, :eid, :meta)", [
    'aid'  => $a['id']   ?? null,
    'an'   => $a['name'] ?? 'system',
    'ac'   => $action,
    'en'   => $entity,
    'eid'  => $entityId,
    'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
  ]);
}
