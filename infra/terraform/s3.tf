# ── S3 Log Bucket ─────────────────────────────────────────────────────────────
resource "aws_s3_bucket" "logs" {
  bucket        = var.s3_log_bucket
  force_destroy = false

  tags = {
    Name        = "${var.project_name}-logs"
    Environment = var.environment
  }
}

# Block all public access
resource "aws_s3_bucket_public_access_block" "logs" {
  bucket                  = aws_s3_bucket.logs.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# Versioning (for audit trail)
resource "aws_s3_bucket_versioning" "logs" {
  bucket = aws_s3_bucket.logs.id
  versioning_configuration { status = "Enabled" }
}

# Lifecycle: move to Glacier after 90 days, delete after 365
resource "aws_s3_bucket_lifecycle_configuration" "logs" {
  bucket = aws_s3_bucket.logs.id

  rule {
    id     = "archive-old-logs"
    status = "Enabled"

    transition {
      days          = 90
      storage_class = "GLACIER"
    }

    expiration { days = 365 }
  }
}

# Server-side encryption
resource "aws_s3_bucket_server_side_encryption_configuration" "logs" {
  bucket = aws_s3_bucket.logs.id
  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}
