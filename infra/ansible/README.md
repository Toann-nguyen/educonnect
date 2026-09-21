# Ansible deploy production educonnect

Chỉ `compose up` 10 services đang chạy — không provision mới,
không đọc/template `.env`, không động vào `data/*`.

## Cài collection (1 lần)

```bash
cd /home/robert/educonnect/infra/ansible
ansible-galaxy collection install -r requirements.yml
```

## Chạy thử trước (bắt buộc, an toàn — không đổi gì)

```bash
ansible-playbook playbook.yml --check --diff
```

## Chạy thật (prod đang sống — chủ nhân tự chạy)

```bash
ansible-playbook playbook.yml
```

## Kiểm tra syntax sau khi sửa playbook

```bash
ansible-playbook playbook.yml --syntax-check
```
