function getDimensions(elem){
	
	var canvasLeft = $('#canvasBuildSpace').offset().left;
	var canvasTop = $('#canvasBuildSpace').offset().top;
	var elemLeft = $(elem).offset().left;
	var elemTop = $(elem).offset().top;
	
	var elemLeftOrig = elemLeft;
	var elemTopOrig = elemTop;
	
	var elemWidth = $(elem).width();
	var elemHeight = $(elem).height();
	var elemCenterX = elemLeft - canvasLeft + (elemWidth / 2);
	var elemCenterY = elemTop - canvasTop  + (elemHeight / 2);
	var elemLeft = elemLeft - canvasLeft;
	var elemRight = elemLeft + elemWidth;
	var elemRightOrig = elemLeftOrig + elemWidth;
	var elemTop = elemTop - canvasTop;
	var elemBottom = elemTop + elemHeight;
	
	var dimensions = {
		leftOrig: elemLeftOrig,
		topOrig: elemTopOrig,
		rightOrig: elemRightOrig,
		left: elemLeft,
		right: elemRight,
		top: elemTop,
		bottom: elemBottom,
		centerX: elemCenterX,
		centerY: elemCenterY,
		width: elemWidth,
		height: elemHeight
	};
	return dimensions;
}

function drawCabinetConnections(elementArray){
	cabinetCtx.strokeStyle = 'LightSkyBlue';
	cabinetCtx.lineWidth = 3;
	cabinetCtx.beginPath();
	//console.log('Debug (elementArray): '+JSON.stringify(elementArray));
	
	$.each(elementArray, function(index, element){
		var elemA = element[0];
		var elemB = element[1];
		
		var connectionStyle = $('#connectionStyle').val();
		
		var elemACabinet = $(elemA).closest('.cabinetContainer');
		var elemACabinetID = $(elemACabinet).data('cabinetId');
		var elemACabinetDimensions = getDimensions(elemACabinet);
		var elemADimensions = getDimensions(elemA);
		var elemAPartition = $(elemA).closest('.partition');
		var elemAPartitionDimensions = getDimensions(elemAPartition);
		
		cabinetCtx.moveTo(elemADimensions.centerX, elemADimensions.centerY);
		
		if(typeof elemB == 'object') {
			var elemBCabinet = $(elemB).closest('.cabinetContainer');
			var elemBCabinetID = $(elemBCabinet).data('cabinetId');
			var elemBCabinetDimensions = getDimensions(elemBCabinet);
			var elemBDimensions = getDimensions(elemB);
			var elemBPartition = $(elemB).closest('.partition');
			var elemBPartitionDimensions = getDimensions(elemBPartition);

			if(elemBDimensions.top >= elemADimensions.top) {
				var elemAPartHBoundary = elemAPartitionDimensions.bottom;
				var elemBPartHBoundary = elemBPartitionDimensions.top;
			} else {
				var elemAPartHBoundary = elemAPartitionDimensions.top;
				var elemBPartHBoundary = elemBPartitionDimensions.bottom;
			}
			
			if(connectionStyle == 0) {
				cabinetCtx.lineTo(elemADimensions.centerX, elemAPartHBoundary);
				cabinetCtx.lineTo(elemACabinetDimensions.left - canvasInset, elemAPartHBoundary);
				
				if(elemACabinetID != elemBCabinetID) {
					
					// Ports are in different cabinets
					if(elemACabinetDimensions.top >= elemBCabinetDimensions.top) {
						
						// Connection should be routed up
						var elemACabinetHBoundary = elemACabinetDimensions.top - canvasInset;
					} else {
						
						// Connection should be routed down
						var elemACabinetHBoundary = elemACabinetDimensions.bottom + canvasInset;
					}
					
					cabinetCtx.lineTo(elemACabinetDimensions.left - canvasInset, elemACabinetHBoundary);
					cabinetCtx.lineTo(elemBCabinetDimensions.left - canvasInset, elemACabinetHBoundary);

				}
				cabinetCtx.lineTo(elemBCabinetDimensions.left - canvasInset, elemBPartHBoundary);
				cabinetCtx.lineTo(elemBDimensions.centerX, elemBPartHBoundary);
				cabinetCtx.lineTo(elemBDimensions.centerX, elemBDimensions.centerY);
			} else if(connectionStyle == 1) {
				cabinetCtx.lineTo(elemBDimensions.centerX, elemBDimensions.centerY);
			} else if(connectionStyle == 2) {
				var arcSize = 30;
				cabinetCtx.bezierCurveTo((elemADimensions.centerX - arcSize), elemADimensions.centerY, (elemBDimensions.centerX - arcSize), elemBDimensions.centerY, elemBDimensions.centerX, elemBDimensions.centerY);
			} else {
				cabinetCtx.lineTo(elemBDimensions.centerX, elemBDimensions.centerY);
			}
		} else {
			cabinetCtx.lineTo(elemADimensions.centerX, elemAPartitionDimensions.top);
			cabinetCtx.lineTo(elemACabinetDimensions.left - canvasInset, elemAPartitionDimensions.top);
			cabinetCtx.lineTo(elemACabinetDimensions.left - canvasInset, elemACabinetDimensions.top - canvasInset);
			
			var left = elemACabinetDimensions.leftOrig - canvasInset;
			var top = elemACabinetDimensions.topOrig - canvasInset;
			addCabButton(left, top, elemB);
		}
		
	});
	cabinetCtx.stroke();
}

function drawPathConnections(){
	var pathConections = $(document).data('pathConections');
	pathCtx.strokeStyle = 'LightSkyBlue';
	pathCtx.lineWidth = 3;
	pathCtx.beginPath();
	
	$.each(pathConections, function(index, element){
		var elemA = element[0];
		var elemB = element[1];
		
		var elemADimensions = getDimensions(elemA);
		var elemBDimensions = getDimensions(elemB);
		
		pathCtx.moveTo(elemADimensions.centerX, elemADimensions.centerY);
		pathCtx.lineTo(elemBDimensions.centerX, elemBDimensions.centerY);
	});
	pathCtx.stroke();
}

function drawTrunk(elementArray){
	cabinetCtx.strokeStyle = 'MidnightBlue';
	cabinetCtx.lineWidth = 3;
	cabinetCtx.beginPath();
	
	$.each(elementArray, function(index, element){
		
		var vertical = canvasInset + (5*index);
		
		var elemA = element[0];
		var elemB = element[1];
	
		var canvasDimensions = getDimensions($('#canvasBuildSpace'));
		var elemACabinet = $(elemA).closest('.cabinetContainer');
		var elemACabinetID = $(elemACabinet).data('cabinetId');
		var elemACabinetDimensions = getDimensions($(elemA).closest('.cabinetContainer'));
		var elemADimensions = getDimensions(elemA);
		
		cabinetCtx.moveTo(elemADimensions.right, elemADimensions.centerY);
		
		if(typeof elemB == 'object') {
			
			var elemBCabinet = $(elemB).closest('.cabinetContainer');
			var elemBCabinetID = $(elemBCabinet).data('cabinetId');
			var elemBCabinetDimensions = getDimensions($(elemB).closest('.cabinetContainer'));
			var elemBDimensions = getDimensions(elemB);
			
			cabinetCtx.lineTo(elemACabinetDimensions.right + vertical, elemADimensions.centerY);
			
			if(elemACabinetID != elemBCabinetID) {
				if(elemACabinetDimensions.top <= elemBCabinetDimensions.top) {
					elemBCabinetHBoundary = elemBCabinetDimensions.top - vertical;
				} else {
					elemBCabinetHBoundary = elemBCabinetDimensions.bottom + vertical;
				}
				cabinetCtx.lineTo(elemACabinetDimensions.right + vertical, elemBCabinetHBoundary);
				cabinetCtx.lineTo(elemBCabinetDimensions.right + vertical, elemBCabinetHBoundary);
			}
			cabinetCtx.lineTo(elemBCabinetDimensions.right + vertical, elemBDimensions.centerY);
			cabinetCtx.lineTo(elemBDimensions.right, elemBDimensions.centerY);

		} else {
			cabinetCtx.lineTo(elemACabinetDimensions.right + vertical, elemADimensions.centerY);
			cabinetCtx.lineTo(elemACabinetDimensions.right + vertical, elemACabinetDimensions.top - vertical);
			cabinetCtx.strokeRect(elemADimensions.left, elemADimensions.top, elemADimensions.width, elemADimensions.height);
			
			var left = elemACabinetDimensions.rightOrig + vertical;
			var top = elemACabinetDimensions.topOrig - vertical;
			addCabButton(left, top, elemB);
		}
		
	});
	cabinetCtx.stroke();
}

function addCabButton(left, top, globalID){
	var addCab = $('<a class="addCabButton" data-global-id="'+globalID+'" href="#"style="z-index:1001; position:absolute;"><i class="fa fa-plus" style="color:#039cfd; background-color:white;"></i></a>');
	$('#canvasBuildSpace').after(addCab);
	var left = left - ($(addCab).width()/2);
	var top = top - ($(addCab).height()/2);
	$(addCab).css({'left':left+'px', 'top':top+'px'});
	makeAddCabButtonClickable(addCab);
}

function highlightElement(elemArray, color){
	$.each(elemArray, function(index, elem){
		cabinetCtx.strokeStyle = color;
		cabinetCtx.beginPath();
		
		var elemDimensions = getDimensions(elem);
		
		cabinetCtx.strokeRect(elemDimensions.left, elemDimensions.top, elemDimensions.width, elemDimensions.height);
	});
}

function clearPaths(){
	var canvasHeight = $('#canvasBuildSpace').height();
	var canvasWidth = $('#canvasBuildSpace').width();
	cabinetCtx.clearRect(0, 0, canvasWidth, canvasHeight);
	$('a.addCabButton').remove();
	drawPathConnections();
}

function clearPathConnections(){
	var canvasHeight = $('#canvasBuildSpace').height();
	var canvasWidth = $('#canvasBuildSpace').width();
	pathCtx.clearRect(0, 0, canvasWidth, canvasHeight);
}

function crawlPath(selectedPort){
	var portArray = [];
	var connectionArray = [];
	var trunkArray = [];
	var partitionArray = [];
	var selectedPeerID = $(selectedPort).data('peerGlobalId');
	var selectedPortOrig = selectedPort;
	
	for(x=0; x<2; x++) {
		
		if(x == 1) {
			
			// Crawl trunk peer
			var selectedPartition = $(selectedPortOrig).closest('.partition');
			var selectedPartitionPeerID = $(selectedPartition).data('peerGlobalId');
			
			if($('#'+selectedPartitionPeerID).length) {
				var selectedPartitionPeer = $('#'+selectedPartitionPeerID);
				trunkArray.push([selectedPartition, selectedPartitionPeer]);
				partitionArray.push(selectedPartition, selectedPartitionPeer);
				
				var selectedPartitionPeerIDArray = selectedPartitionPeerID.split('-');
				var peerID = selectedPartitionPeerIDArray[2];
				var peerFace = selectedPartitionPeerIDArray[3];
				var peerDepth = selectedPartitionPeerIDArray[4];
				var peerPort = $(selectedPortOrig).data('portIndex');
				
				var selectedPort = $('#port-4-'+peerID+'-'+peerFace+'-'+peerDepth+'-'+peerPort);
			} else {
				if(selectedPartitionPeerID != 'none') {
					trunkArray.push([selectedPartition, selectedPartitionPeerID]);
				}
				var selectedPort = false;
			}
		}
		
		while($(selectedPort).length) {
			portArray.push(selectedPort);
			
			// Crawl connection peer
			var connectedPortIDString = $(selectedPort).data('connectedGlobalId');
			var connectedPortIDArray = JSON.parse(atob(connectedPortIDString));
			
			if(connectedPortIDArray.length) {
				var peerPortFound = false;
				$.each(connectedPortIDArray, function(index, connectedPortID){
					var connectedPort = $('#'+connectedPortID);
					if($(connectedPort).length) {
						
						portArray.push(connectedPort)
						connectionArray.push([selectedPort, connectedPort]);
						
						var connectedPartition = $(connectedPort).closest('.partition');
						var connectedPartitionPeerID = $(connectedPartition).data('peerGlobalId');
						
						if($('#'+connectedPartitionPeerID).length) {
							
							var connectedPartitionPeer = $('#'+connectedPartitionPeerID);
							trunkArray.push([connectedPartition, connectedPartitionPeer]);
							partitionArray.push(connectedPartition, connectedPartitionPeer);
							
							var connectedPartitionPeerIDArray = connectedPartitionPeerID.split('-');
							var peerID = connectedPartitionPeerIDArray[2];
							var peerFace = connectedPartitionPeerIDArray[3];
							var peerDepth = connectedPartitionPeerIDArray[4];
							
							var connectedPortIDArray = connectedPortID.split('-');
							var peerPort = connectedPortIDArray[5];
							selectedPort = $('#port-4-'+peerID+'-'+peerFace+'-'+peerDepth+'-'+peerPort);
							peerPortFound = true;
						} else {
							if(connectedPartitionPeerID != 'none') {
								trunkArray.push([connectedPartition, connectedPartitionPeerID]);
							}
						}
						
					} else {
						connectionArray.push([selectedPort, connectedPortID]);
					}
				});
				if(peerPortFound == false) {
					selectedPort = false;
				}
			} else {
				selectedPort = false;
			}
		}
	}
	
	return {
		'partitionArray': partitionArray,
		'trunkArray': trunkArray,
		'portArray': portArray,
		'connectionArray': connectionArray
	};
}

function crawlPathConnections(){
	var pathConections = {};
	var connectorElementArray = $('#containerFullPath').find('.port');
	$.each(connectorElementArray, function(index, element){
		if($(element).data('connectionPairId') !== undefined) {
			var connectionPairID = $(element).data('connectionPairId');
			if(pathConections[connectionPairID] === undefined) {
				console.log('here3');
				pathConections[connectionPairID] = [];
			}
			pathConections[connectionPairID].push($(element));
		}
	});
	$(document).data('pathConections', pathConections);
}

function drawPath(){
	clearPathConnections();
	crawlPathConnections();
	drawPathConnections();
}

function makePortsHoverable(){
	
	resizeCanvas();
	redraw();
	
	$('#buildSpaceContent').find('.port').each(function(){
		$(this).unbind('mouseenter mouseleave click.drawConnections');
	});
	$('#buildSpaceContent').find('.port').each(function(){
		$(this).hover(function(){
			
			var pathElementArray = crawlPath(this);
			
			highlightElement(pathElementArray['partitionArray'], 'black');
			drawTrunk(pathElementArray['trunkArray']);
			highlightElement(pathElementArray['portArray'], 'LightSkyBlue');
			drawCabinetConnections(pathElementArray['connectionArray']);
			
		}, function(){
			redraw();
		});
		
		$(this).on('click.drawConnections', function(){
			
			if(typeof $(this).data('pathID') == 'undefined') {
				
				var pathElementArray = crawlPath(this);
				
				// Get pathID
				// This allows persistant paths to accumulate
				//pathID++;
				// This allows only 1 persistant path at a time
				pathID = 0;
				if(typeof $(document).data('cabinetConnections')[pathID] != 'undefined') {
					$.each($(document).data('cabinetConnections')[pathID]['portArray'], function(){
						$(this).removeData('pathID');
					});
				}
				
				// Associate ports to pathIDs
				$.each(pathElementArray['portArray'], function(){
					$(this).data('pathID', pathID);
				});
				
				// Store connection path
				var workingPathData = {
					'portArray': pathElementArray['portArray'],
					'connectionArray': pathElementArray['connectionArray'],
					'trunkArray': pathElementArray['trunkArray'],
					'partitionArray': pathElementArray['partitionArray']
				};
				$(document).data('cabinetConnections')[pathID] = workingPathData;
				
			} else {
				
				// Clear pathIDs from ports
				var thisPathID = $(this).data('pathID');
				$.each($(document).data('cabinetConnections')[thisPathID]['portArray'], function(){
					$(this).removeData('pathID');
				});
				
				// Clear connection path
				delete $(document).data('cabinetConnections')[thisPathID];
			}
		});
	});

}

function refreshPathData(){
	var cabinetConnections = $(document).data('cabinetConnections');
	pathDataOrig = pathData;
	$.each(pathDataOrig, function(pathID, path){
		$.each(path['portArray'], function(portIndex, port){
			var portID = $(port).attr('id');
			if($('#'+portID).length) {
				var pathElementArray = crawlPath($('#'+portID));
				
				// Associate ports to pathIDs
				$.each(pathElementArray['portArray'], function(){
					$(this).data('pathID', pathID);
				});
				
				// Store connection path
				var workingCabinetConnections = {
					'portArray': pathElementArray['portArray'],
					'connectionArray': pathElementArray['connectionArray'],
					'trunkArray': pathElementArray['trunkArray'],
					'partitionArray': pathElementArray['partitionArray']
				};
				$(document).data('cabinetConnections', workingCabinetConnections);
				
				return false;
			}
		});
	});
}

function makeCabArrowsClickable(){
	$('.cabMoveArrow').unbind('click');
	
	$('.cabMoveArrow').click(function(){
		var direction = $(this).data('cabMoveDirection');
		var cabinet = $(this).closest('.diagramCabinetContainer');
		if(direction == 'left') {
			$(cabinet).insertBefore($(cabinet).prev()).animate();
		} else {
			$(cabinet).insertAfter($(cabinet).next());
		}
		redraw();
	});
}

function makeCabCloseClickable(){
	$('.cabClose').unbind('click');
	
	$('.cabClose').click(function(){
		var cabinet = $(this).closest('.diagramCabinetContainer');
		var locationBoxes = $(cabinet).parents('.diagramLocationBox');
		
		// Delete cabinet
		$(cabinet).remove();
		
		// Clean up empty location boxes
		$(locationBoxes).each(function(){
			if(!$(this).find('.diagramCabinetContainer').length) {
				$(this).remove();
			}
		});
		
		// Refresh all paths
		refreshPathData();
		
		redraw();
	});
}

function resizeCanvas() {
	$('#canvasBuildSpace').attr('width', $(document).width());
	$('#canvasBuildSpace').attr('height', $(document).height());
}

function redraw() {
	clearPaths();
	var cabinetConnections = $(document).data('cabinetConnections');
	if(typeof cabinetConnections != 'undefined') {
		$.each(cabinetConnections, function(pathID, path){
			drawTrunk(path['trunkArray']);
			highlightElement(path['partitionArray'], 'MidnightBlue');
			drawCabinetConnections(path['connectionArray']);
			highlightElement(path['portArray'], 'LightSkyBlue');
		});
	}
}

function initializeCanvas() {
	
	window.addEventListener('resize', resizeCanvas, false);
	htmlCanvas = document.getElementById('canvasBuildSpace');
	var lineWidth = 10;
	
	// Cabinet connections
	cabinetCtx = htmlCanvas.getContext('2d');
	cabinetCtx.lineWidth = lineWidth;
	$(document).data('cabinetConnections', {});
	canvasInset = 10;
	pathID = 0;
	
	// Path connections
	pathCtx = htmlCanvas.getContext('2d');
	pathCtx.lineWidth = lineWidth;
	$(document).data('pathConections', {});
	
}
