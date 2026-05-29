flowchart TD
    Client((Client)) -->|1. POST /api/v1/auth/register<br>name, email, password| APIGateway[API Gateway]
    
    APIGateway -->|2. Rate Limit Check| Redis[(Redis <br> Rate Limiter)]
    Redis -->|3. If exceeded| Error429[Return 429 Too Many Requests]
    
    APIGateway -->|4. Validate Request| Validation[Request Validation]
    Validation -->|5. If invalid| Error400[Return 400 Bad Request]
    
    Validation -->|6. Check Idempotency Key| RedisIdempotency[(Redis <br> Idempotency Store)]
    RedisIdempotency -->|7. If key exists| Error409[Return 409 Conflict]
    
    Validation -->|8. Check Email Exists| DB[(PostgreSQL <br> Users Table)]
    DB -->|9. If exists| Error409[Return 409 Conflict]
    
    DB -->|10. Create User with status: UNVERIFIED| DB
    DB -->|11. Generate Verification Token| TokenGen[Token Generator]
    TokenGen -->|12. Save Token to Redis<br>TTL: 24 hours| RedisToken[(Redis <br> Verification Tokens)]
    
    %% Async Processing Branch
    DB -->|13. Dispatch Job to Queue| RedisQueue[(Redis Queue <br> BullMQ/Horizon)]
    RedisQueue -->|14. Send Verification Email| Mailer[Mail Service <br> SendGrid/Resend]
    RedisQueue -->|15. Publish UserRegisteredEvent| EventBridge[Event Bus <br> Kafka/RabbitMQ]
    
    EventBridge -->|16. Notify Services| Marketing[Marketing Service]
    EventBridge -->|17. Notify Services| Analytics[Analytics Service]
    EventBridge -->|18. Notify Services| Notification[Notification Service]
    
    %% Immediate Response
    DB -->|19. Return 201 Created<br>user_id, email, status: UNVERIFIED| APIGateway
    APIGateway -->|20. Return Response| Client
    
    %% Background Worker Processing
    subgraph "Background Workers (Horizon/BullMQ)"
        Worker[Queue Worker] -->|21. Process Job| RedisQueue
        Worker -->|22. Handle Failures| DeadLetter[Dead Letter Queue]
        Worker -->|23. Retry Logic| RedisQueue
    end
    
    %% Verification Flow (Next Step)
    Client -->|24. Click Email Link<br>GET /verify/email?token=xxx| VerificationFlow[Email Verification Flow]