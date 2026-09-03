'use strict';

/**
 * Test client cho realtime-service:
 * 1. Kết nối Socket.IO qua gateway (JWT handshake)
 * 2. Đăng ký lắng nghe 'notification'
 * 3. Publish message vào finance_events → nhận push
 *
 * Usage: node test/consumer.test.js [gateway_url] [user_id]
 */

const { io } = require('socket.io-client');
const jwt = require('jsonwebtoken');
const amqp = require('amqplib');

const GATEWAY = process.argv[2] || 'http://localhost:8080';
const USER_ID = process.argv[3] || '1';
const JWT_SECRET = process.env.JWT_SECRET || 'RXbo2pz3j0kfLUrujwlZ2TOmTpYBpMSLYYx3kaDmjvGHUypVYJSnaR22oQzyIU8D';

const token = jwt.sign({ sub: USER_ID, roles: [] }, JWT_SECRET, { expiresIn: '1h' });

async function main() {
  // ── 1. Connect WebSocket qua gateway ──
  const socket = io(GATEWAY, {
    path: '/socket.io',
    auth: { token },
    transports: ['websocket'],
    timeout: 5000,
  });

  const gotNotification = new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error('TIMEOUT: không nhận được notification trong 10s')), 10000);
    socket.on('notification', (payload) => {
      clearTimeout(t);
      resolve(payload);
    });
    socket.on('connect_error', (err) => {
      clearTimeout(t);
      reject(err);
    });
  });

  await new Promise((resolve, reject) => {
    socket.on('connected', resolve);
    socket.on('connect_error', reject);
    setTimeout(() => reject(new Error('WS connect timeout')), 5000);
  });
  console.log(`✅ Socket.IO connected qua ${GATEWAY} (user:${USER_ID})`);

  // ── 2. Publish vào finance_events ──
  // Lưu ý: notification_queue có 2 consumer (notify + realtime) round-robin
  // → publish nhiều message để đảm bảo realtime nhận ít nhất 1
  const conn = await amqp.connect(process.env.RABBITMQ_URL || 'amqp://educonnect:educonnect_dev@localhost:5672');
  const ch = await conn.createChannel();
  for (let i = 0; i < 4; i++) {
    const payload = {
      type: 'email',
      to: `parent${i}@edu.vn`,
      subject: 'Hóa đơn học phí tháng 9',
      body: '<p>Quý phụ huynh có hóa đơn mới cần thanh toán.</p>',
      user_id: Number(USER_ID),
    };
    ch.publish('finance_events', 'finance.invoice.paid', Buffer.from(JSON.stringify(payload)), {
      headers: { 'x-correlation-id': `test-e2e-00${i}` },
    });
  }
  console.log(`📤 Published 4x finance.invoice.paid → notification_queue`);
  await ch.close();
  await conn.close();

  // ── 3. Chờ push ──
  const received = await gotNotification;
  console.log(`📥 NHẬN ĐƯỢC PUSH:`, JSON.stringify(received));
  console.log('✅ E2E REALTIME OK');
  socket.close();
  process.exit(0);
}

main().catch((err) => {
  console.error('❌ E2E REALTIME FAIL:', err.message);
  process.exit(1);
});
