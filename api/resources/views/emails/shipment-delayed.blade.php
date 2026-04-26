<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px; }
        .header { background-color: #f8f9fa; padding: 10px 20px; border-bottom: 2px solid #e9ecef; }
        .content { padding: 20px 0; }
        .footer { font-size: 0.8em; color: #777; margin-top: 20px; text-align: center; }
        .badge { display: inline-block; padding: 5px 10px; border-radius: 3px; background-color: #ffc107; color: #000; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>FabricFlow Update</h2>
        </div>
        <div class="content">
            <p>Dear {{ $batch->buyer_name ?? 'Valued Customer' }},</p>
            
            <p>This is an automated update regarding your fabric order.</p>
            
            <p>Your batch <strong>{{ $batch->batch_id }}</strong> ({{ $batch->quantity_kg }} kg of {{ ucfirst($batch->fabric_type) }}) is currently at the <span class="badge">{{ $batch->current_stage }}</span> stage.</p>
            
            <p>Please note that this stage is currently experiencing a delay of approximately <strong>{{ number_format($hoursOverdue, 1) }} hours</strong> beyond the expected timeframe.</p>
            
            <p>Our manufacturing team has been alerted and is actively working to expedite the process. We will keep you updated as the batch moves to the next stage.</p>
            
            <p>If you have any questions, please contact your supplier at {{ $batch->supplier_contact ?? 'our support desk' }}.</p>
            
            <p>Thank you for your patience.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} FabricFlow Logistics. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
