# Namespace educonnect (import hoặc tạo nếu chưa có)
resource "kubernetes_namespace" "educonnect" {
  metadata {
    name = "educonnect"
  }
}

# ResourceQuota giới hạn RAM 4Gi (request) / 6Gi (limit) và tối đa 20 Pods
resource "kubernetes_resource_quota" "educonnect_quota" {
  metadata {
    name      = "educonnect-quota"
    namespace = kubernetes_namespace.educonnect.metadata[0].name
  }

  spec {
    hard = {
      "requests.memory" = "4Gi"
      "limits.memory"   = "6Gi"
      "pods"            = "20"
    }
  }
}

# Namespace ingress-nginx
resource "kubernetes_namespace" "ingress_nginx" {
  metadata {
    name = "ingress-nginx"
  }
}

# Helm Release NGINX Ingress Controller
resource "helm_release" "nginx_ingress" {
  name       = "ingress-nginx"
  repository = "https://kubernetes.github.io/ingress-nginx"
  chart      = "ingress-nginx"
  namespace  = kubernetes_namespace.ingress_nginx.metadata[0].name
  version    = "4.10.0"

  set {
    name  = "controller.service.type"
    value = "NodePort"
  }

  set {
    name  = "controller.resources.requests.cpu"
    value = "100m"
  }

  set {
    name  = "controller.resources.requests.memory"
    value = "90Mi"
  }
}
