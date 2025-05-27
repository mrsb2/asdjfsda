<?php
/**
 * PDF Pass: Styles
 */
?>
<style>
table {
    border-collapse: collapse;
    border-spacing: 0;
}




	/* Main ticket container - 210x74mm */
	.tec-tickets__wallet-plus-pdf-table {
		color: #141827 ;
		font-family: Arial, sans-serif ;
		width: 210mm ;
		height: 65mm ;
		margin: 0mm ;
		padding: 0mm ;
		border-collapse: collapse ;
		background: white ;
		border-spacing: 0;
	}


	/* Main content row */
	.tec-tickets__wallet-plus-pdf-main-row {
		height: 55mm ; /* Slightly increased from 60mm */
	}
	
	/* Event image cell */
	.tec-tickets__wallet-plus-pdf-image-cell {
		width: 72mm ;
		height: 55mm ;
		padding: 0 ;
		margin: 0 ;
		vertical-align: top ;

		line-height: 0;


		/*border-right: 1pt solid #ddd ;*/
	}
	
	/* Ticket info cell - ADDED 2MM SPACING FROM IMAGE */
	.tec-tickets__wallet-plus-pdf-info-cell {
		width: 80mm ; /* Keep original width */
		height: 55mm ;
		padding: 0pt ;
		vertical-align: top ;
		line-height: 1;
	}

	
	/* QR code cell */
	.tec-tickets__wallet-plus-pdf-qr-cell {
		width: 55mm ; /* Back to original */
		height: 55mm ;
		text-align: center ;
		vertical-align: top ;
		box-sizing: content-box;
		padding: 0pt ;
		line-height: 0;
	}
	
	/* Event title */
	.tec-tickets__wallet-plus-pdf-post-title {
		font-family: Arial, sans-serif ;
		font-weight: bold ;
		font-size: 15pt ;
		color: #333 ;
		line-height: 1.1 ;
		padding: 0 ;
	}
	
	/* Info rows */
	.tec-tickets__wallet-plus-pdf-info-row {
		/*margin-bottom: 1pt ;*/
		display: block ;
		line-height: 1.2 ;
	}
	
	.tec-tickets__wallet-plus-pdf-info-label {
		font-weight: 600 ;
		color: #333 ;
		font-size: 9pt ;
		width: 28mm ;
		display: inline-block ;
		vertical-align: top ;
	}
	
	.tec-tickets__wallet-plus-pdf-info-value {
		color: #555 ;
		font-size: 9pt ;
		vertical-align: top ;
	}
	
	/* Company info */
	.tec-tickets__wallet-plus-pdf-company-info {
		font-size: 7pt ;
		color: #888 ;
		border-top: 1pt solid #eee ;
		padding-top: 2pt ;
		margin-top: 4pt ;
		line-height: 1.1 ;
	}
	
	/* Image styling - OBJECT-FIT NONE (NO CROPPING) */
	.tec-tickets__wallet-plus-pdf-event-image {
		width: 70mm ;
		height: 55mm ;

	}
	
	/* QR Code styling */
	.tec-tickets__wallet-plus-pdf-qr-image {
		width: 55mm ;
		height: 55mm ;
		background: white ;
	}
	
	/* Footer styling - CMYK BLACK 100% - BROUGHT CLOSER */
	.tec-tickets__wallet-plus-pdf-footer-row {
		height: 16mm; /* Increased footer height to fill remaining space */
		background: #000000 ;
		background-color: #000000 ;
		line-height: 0;
	}
	
	.tec-tickets__wallet-plus-pdf-footer-cell {
		text-align: center ;
		vertical-align: middle ;
		padding: 0pt ;
		height: 16mm ;
		background: #000000 ;
		background-color: #000000 ;
		line-height: 0;
	}
	

</style>