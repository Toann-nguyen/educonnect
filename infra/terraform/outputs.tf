output "educonnect_namespace" {
  value       = kubernetes_namespace.educonnect.metadata[0].name
  description = "Tên namespace k3s chính cho ứng dụng EduConnect"
}

output "nginx_ingress_status" {
  value       = helm_release.nginx_ingress.status
  description = "Trạng thái triển khai NGINX Ingress Controller"
}
