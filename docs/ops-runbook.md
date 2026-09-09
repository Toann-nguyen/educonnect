# Ops Runbook — educonnect (k3s single-node)

> Quy ước: mọi lệnh kubectl cần `export KUBECONFIG=~/.kube/config`

## Step 1 — Monitoring fix (2026-09-08, rev6 `deployed`)

Triệu chứng:
- Grafana `CrashLoopBackOff`, `Last State: OOMKilled (137)` — limit 256Mi quá thấp
  (thực đo pod mới dùng ~500Mi).
- Operator `ContainerCreating`: `secret prometheus-stack-kube-prom-admission not found`
  — chart mount secret vô điều kiện, nhưng job certgen tạo secret đã bị tắt.

Cách sửa (đã áp dụng trong `infra/helm-charts/monitoring/prometheus-values.yaml`):
- Grafana `limits.memory 256Mi → 768Mi`, requests 256Mi.
- `prometheusOperator.admissionWebhooks.enabled: false → true`,
  `patch.enabled: true` (certgen tạo admission secret).
- Xóa `adminPassword` plaintext khỏi values → dùng secret
  `prometheus-stack-grafana-admin` (`admin-user`/`admin-password`) + `admin.existingSecret`.
- Rotate password Grafana qua API (`PUT /api/user/password`) vì PVC cũ giữ hash cũ
  trong sqlite — secret mới chỉ có tác dụng ở lần init đầu.

Verify:
- `kubectl get pods -n monitoring` — 6/6 Running (prometheus, alertmanager,
  grafana 3/3, operator, kube-state-metrics, node-exporter), 0 restart.
- Prometheus `/api/v1/targets` — 13/13 `up`.
- Grafana `/api/org` login pass mới 200, pass cũ 401.
- Password Grafana mới: lưu trong secret cluster + báo riêng (không commit).
  Password cũ đã lộ trong Git history → coi như vô hiệu (đã rotate).

Chưa xong:
- Theo dõi OOM 60 phút sau fix.
- Secret `prometheus-stack-grafana-admin` chỉ sống trong cluster — rebuild cluster
  phải tạo lại (ghi vào BACKUP_RESTORE.md ở Step 6).
