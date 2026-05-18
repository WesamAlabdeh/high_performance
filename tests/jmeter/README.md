# Apache JMeter — Stress Test (100 users)

Per course requirements, use **Apache JMeter** to simulate 100 concurrent users on the full API.

## Install JMeter

Download: https://jmeter.apache.org/download_jmeter.cgi

## Test plan setup

1. **Thread Group**
   - Number of Threads: `100`
   - Ramp-Up: `10` seconds
   - Loop Count: `5`

2. **HTTP Request Defaults**
   - Server: `127.0.0.1`
   - Port: `8000`

3. **CSV Data Set Config** (optional — multiple tokens)
   - Or single user: Login once, extract token with JSON Extractor

4. **Requests sequence**
   - `POST /api/auth/login` → JSON Extractor `token` = `$.data.token`
   - `GET /api/product` — Header `Authorization: Bearer ${token}`
   - `GET /api/wallet`
   - `POST /api/cart` body `{"product_id":1,"quantity":1}`
   - `POST /api/order` body `{"user_notes":"jmeter"}`

5. **Listeners**
   - Aggregate Report
   - View Results Tree (debug only)
   - Save results to `storage/app/benchmarks/jmeter-results.jtl`

## Before running

```bash
# Terminal 1
composer octane

# Terminal 2
php artisan queue:work --queue=invoices,notifications,batches,default

# Terminal 3 — monitoring
composer monitoring
# Grafana: http://localhost:3000 (admin/admin)

# Set low payment delay for stress
PAYMENT_SIMULATION_DELAY_MS=0
```

## Alternative (built-in)

```bash
php artisan benchmark:stress --users=100
php artisan benchmark:bottlenecks
```
