'use strict';

/**
 * educonnect-realtime — WebSocket gateway cho notification
 * --------------------------------------------------------
 * - Socket.IO server (đứng sau Nginx gateway tại /socket.io/)
 * - Consumer RabbitMQ: notification_queue (user_events + finance_events)
 * - Auth: JWT trong handshake (cùng secret với identity service)
 * - Route: message → room `user:{user_id}` + room `admins`
 */

const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const amqp = require('amqplib');
const jwt = require('jsonwebtoken');

const PORT = process.env.PORT || 3000;
const RABBITMQ_URL = `amqp://${process.env.RABBITMQ_USER || 'educonnect'}:${process.env.RABBITMQ_PASSWORD || 'educonnect_dev'}@${process.env.RABBITMQ_HOST || 'rabbitmq'}:${process.env.RABBITMQ_PORT || 5672}`;
const JWT_SECRET = process.env.JWT_SECRET || 'dev-secret';
const QUEUE = 'notification_queue';
const EXCHANGES = [
  { name: 'user_events', type: 'topic', keys: ['user.#'] },
  { name: 'finance_events', type: 'topic', keys: ['finance.#'] },
];

const app = express();
let wsConnections = 0;

app.get('/health', (req, res) => {
  res.json({ service: 'realtime-service', status: 'healthy', ws_connections: wsConnections });
});

const server = http.createServer(app);
const io = new Server(server, {
  cors: { origin: true, credentials: true },
  path: '/socket.io',
});

// ── JWT auth trong handshake ────────────────────────────────
io.use((socket, next) => {
  const token =
    socket.handshake.auth?.token ||
    (socket.handshake.headers?.authorization || '').replace(/^Bearer\s+/i, '');
  if (!token) return next(new Error('unauthorized'));
  try {
    const payload = jwt.verify(token, JWT_SECRET);
    socket.userId = String(payload.sub ?? payload.user_id ?? payload.id);
    socket.roles = Array.isArray(payload.roles) ? payload.roles : [];
    next();
  } catch {
    next(new Error('invalid_token'));
  }
});

io.on('connection', (socket) => {
  wsConnections++;
  socket.join(`user:${socket.userId}`);
  if (socket.roles.includes('admin')) socket.join('admins');
  socket.emit('connected', { userId: socket.userId });
  socket.on('disconnect', () => wsConnections--);
  socket.on('error', () => {});
});

// ── RabbitMQ consumer ───────────────────────────────────────
async function consume(channel) {
  await channel.assertQueue(QUEUE, {
    durable: true,
    arguments: { 'x-queue-type': 'quorum', 'x-dead-letter-exchange': 'educonnect.dlx' },
  });
  for (const ex of EXCHANGES) {
    await channel.assertExchange(ex.name, ex.type, { durable: true });
    for (const key of ex.keys) {
      await channel.bindQueue(QUEUE, ex.name, key);
    }
  }

  await channel.consume(
    QUEUE,
    (msg) => {
      if (!msg) return;
      let payload;
      try {
        payload = JSON.parse(msg.content.toString());
      } catch {
        channel.nack(msg, false, false); // → DLX
        return;
      }
      const corrId = msg.properties?.headers?.['x-correlation-id'] || '-';
      console.log(`[corr=${corrId}] push ${msg.fields.routingKey} → user:${payload.user_id}`);

      io.to(`user:${payload.user_id}`).emit('notification', payload);
      io.to('admins').emit('notification', payload);
      channel.ack(msg);
    },
    { noAck: false }
  );
}

async function connect() {
  // Retry chờ broker (giống notify service)
  for (let i = 0; i < 30; i++) {
    try {
      const conn = await amqp.connect(RABBITMQ_URL);
      const channel = await conn.createChannel();
      await consume(channel);
      console.log(`📬 Realtime consumer sẵn sàng trên ${QUEUE}`);
      conn.on('close', () => {
        console.error('RabbitMQ connection closed — reconnect sau 5s');
        setTimeout(connect, 5000);
      });
      return;
    } catch (err) {
      console.error(`RabbitMQ chưa sẵn sàng (${i + 1}/30): ${err.message}`);
      await new Promise((r) => setTimeout(r, 2000));
    }
  }
  process.exit(1);
}

server.listen(PORT, () => {
  console.log(`🚀 realtime-service listening on :${PORT}`);
  connect();
});
