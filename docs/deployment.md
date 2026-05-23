# Deployment & Configuration Guide

This document outlines the deployment process for the HBMS web application and its accompanying OCR microservice, including recent infrastructure updates and configuration changes.

## 1. Local Production Build
To prepare the application for deployment, we bundle the necessary files into a clean `production_build` folder:
1. Run `./build_production.sh` locally. This script:
   - Copies the `html/` directory and `migrations/`.
   - Cleans out local development `.env` files.
   - Generates a production-ready `.env` template.
   - Creates a server setup script (`run_on_server.sh`).
2. The output is zipped into `production_build.zip` and uploaded to the production server (`hbms-server`).

## 2. PHP Server Deployment (`hbms-server`)
Once the zip file is uploaded to the production server:
1. **Extraction:** Extract `production_build.zip` into the public web root (e.g., `/var/www/html`).
2. **Permissions:** Run the `./run_on_server.sh` script to set proper write permissions (`775` and `chown apache:apache`) for directories like `keys/`, `images/kyc/`, and `uploads/`.
3. **Database Migrations:** Execute the SQL migration files (such as `V007__add_encryption_columns.sql` and `V008__add_key_fingerprint.sql`) against the production `hbmsdb` database.
4. **Environment Configuration:** Configure the `.env` files (typically placed in `/var/www/.env` and `/var/www/html/.env`) with the actual production Database credentials, OAuth secrets, and OCR configuration.

### SSL / cURL Configuration
To support secure HTTPS communication with external services (like the OCR microservice), the PHP server requires a valid Root CA bundle.
- Ensure `cacert.pem` (downloaded from `https://curl.se/ca/cacert.pem`) is present in the `html/includes/` directory. 
- The `kyc-handler.php` script dynamically loads this CA bundle via `CURLOPT_CAINFO` to bypass SSL verification errors (e.g., cURL error 77).

## 3. OCR Server Deployment & Configuration (GCP)
The OCR microservice is hosted on a separate Google Cloud Platform instance (`binary-exploit-lab`).

### Recent Infrastructure Changes:
1. **Domain & SSL:** 
   - A custom domain (`abooking-ocr.site`) was bound to the GCP instance.
   - Certbot was utilized to provision SSL certificates and enforce HTTPS redirects (HTTP 301).
2. **Firewall Rules:** 
   - A GCP firewall rule (`allow-ocr-5000`) was added to permit ingress traffic on TCP port 5000, allowing external servers to reach the Docker container.
3. **API Key Security:**
   - A cryptographically strong, 64-character hex API key was generated using `openssl rand -hex 32`.
   - The key was updated in the OCR server's `.env` file located at `~/passport-ocr-service/.env`.
   - The `passport-ocr-service` Docker container was restarted using the new environment configuration:
     ```bash
     docker run -d --name passport-ocr-service -p 5000:5000 --env-file .env passport-ocr-service
     ```

## 4. Final Integration
To link the PHP server securely to the OCR backend:
- The PHP `.env` was updated to point to the secure OCR endpoint:
  ```env
  KYC_OCR_URL=https://abooking-ocr.site/api/ocr/passport
  KYC_OCR_API_KEY=75e966505a9308589e7ca3753a172c796816c7425b3adeaceffee0cb1c2d3d7d
  ```
- The `build_production.sh` local template was similarly updated so future builds automatically include the secure configuration.
