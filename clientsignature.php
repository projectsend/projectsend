<style type="text/css">
  #sig-canvas {
    border: 2px dotted #CCCCCC;
    border-radius: 15px;
    cursor: crosshair;
  }
</style>

  <!-- Content -->
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h1>E-Signature</h1>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <canvas id="sig-canvas" width="535" height="160">
          Get a better browser, bro.
        </canvas>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <button class="btn btn-primary" id="sig-submitBtn">Submit Signature</button>
        <button class="btn btn-default" id="sig-clearBtn">Clear Signature</button>
      </div>
    </div>
   <!--  <br/>
    <div class="row">
      <div class="col-md-12">
        <textarea id="sig-dataUrl" class="form-control" rows="5" style="width: 535px;">Data URL for your signature will go here!</textarea>
      </div>
    </div>
    <br/>
    <div class="row">
      <div class="col-md-12">
        <img id="sig-image" src="" alt="Your signature will go here!"/>
      </div>
    </div> -->
  </div>


<script type="text/javascript">
  (function() {
  window.requestAnimFrame = (function(callback) {
    return window.requestAnimationFrame ||
      window.webkitRequestAnimationFrame ||
      window.mozRequestAnimationFrame ||
      window.oRequestAnimationFrame ||
      window.msRequestAnimaitonFrame ||
      function(callback) {
        window.setTimeout(callback, 1000 / 60);
      };
  })();

  var canvas = document.getElementById("sig-canvas");
  var ctx = canvas.getContext("2d");
  ctx.strokeStyle = "#222222";
  ctx.lineWidth = 4;

  var drawing = false;
  var mousePos = {
    x: 0,
    y: 0
  };
  var lastPos = mousePos;

  canvas.addEventListener("mousedown", function(e) {
    drawing = true;
    lastPos = getMousePos(canvas, e);
  }, false);

  canvas.addEventListener("mouseup", function(e) {
    drawing = false;
  }, false);

  canvas.addEventListener("mousemove", function(e) {
    mousePos = getMousePos(canvas, e);
  }, false);

  // Add touch event support for mobile
  canvas.addEventListener("touchstart", function(e) {

  }, false);

  canvas.addEventListener("touchmove", function(e) {
    var touch = e.touches[0];
    var me = new MouseEvent("mousemove", {
      clientX: touch.clientX,
      clientY: touch.clientY
    });
    canvas.dispatchEvent(me);
  }, false);

  canvas.addEventListener("touchstart", function(e) {
    mousePos = getTouchPos(canvas, e);
    var touch = e.touches[0];
    var me = new MouseEvent("mousedown", {
      clientX: touch.clientX,
      clientY: touch.clientY
    });
    canvas.dispatchEvent(me);
  }, false);

  canvas.addEventListener("touchend", function(e) {
    var me = new MouseEvent("mouseup", {});
    canvas.dispatchEvent(me);
  }, false);

  function getMousePos(canvasDom, mouseEvent) {
    var rect = canvasDom.getBoundingClientRect();
    return {
      x: mouseEvent.clientX - rect.left,
      y: mouseEvent.clientY - rect.top
    }
  }

  function getTouchPos(canvasDom, touchEvent) {
    var rect = canvasDom.getBoundingClientRect();
    return {
      x: touchEvent.touches[0].clientX - rect.left,
      y: touchEvent.touches[0].clientY - rect.top
    }
  }

  function renderCanvas() {
    if (drawing) {
      ctx.moveTo(lastPos.x, lastPos.y);
      ctx.lineTo(mousePos.x, mousePos.y);
      ctx.stroke();
      lastPos = mousePos;
    }
  }

  // Prevent scrolling when touching the canvas
  document.body.addEventListener("touchstart", function(e) {
    if (e.target == canvas) {
      e.preventDefault();
    }
  }, false);
  document.body.addEventListener("touchend", function(e) {
    if (e.target == canvas) {
      e.preventDefault();
    }
  }, false);
  document.body.addEventListener("touchmove", function(e) {
    if (e.target == canvas) {
      e.preventDefault();
    }
  }, false);

  (function drawLoop() {
    requestAnimFrame(drawLoop);
    renderCanvas();
  })();

  function clearCanvas() {
    canvas.width = canvas.width;
  }

  // Set up the UI
  var sigText = document.getElementById("sig-dataUrl");
  var sigImage = document.getElementById("sig-image");
  var clearBtn = document.getElementById("sig-clearBtn");
  var submitBtn = document.getElementById("sig-submitBtn");
  clearBtn.addEventListener("click", function(e) {
    clearCanvas();
    // sigText.innerHTML = "Data URL for your signature will go here!";
    // sigImage.setAttribute("src", "");
  }, false);
  submitBtn.addEventListener("click", function(e) {
    var dataUrl = canvas.toDataURL();
    // sigText.innerHTML = dataUrl;
    // sigImage.setAttribute("src", dataUrl);

    // Split the base64 string in data and contentType
    var block = dataUrl.split(";");
    // Get the content type of the image
    var contentType = block[0].split(":")[1];// In this case "image/gif"
    // get the real base64 content of the file
    var realData = block[1].split(",")[1];// In this case "R0lGODlhPQBEAPeoAJosM...."

    // Convert it to a blob to upload
    // var blob = b64toBlob(realData, contentType);
    savepic(realData,$('#uid').val());

  }, false);

})();


function savepic(argument,id) {
  $.ajax({
    url: 'clientsave_sign.php',
    data: { 'img_data':argument,'user_id_mic':id },
    type: 'post',
    dataType: 'json',
    async: false,
    success:function(arg){
      if(arg.status==true){
        $('#sig').modal('toggle');
        $('.sig1').prop("checked", true).trigger('change');
        signaturefun(1);
      }
    }
  });
}
</script>