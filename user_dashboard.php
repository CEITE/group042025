<?php
// ---------------------- PHP BACKEND ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Content-Type: application/json");

    if (!isset($_FILES['image'])) {
        echo json_encode(["error" => "No image uploaded"]);
        exit;
    }

    // Roboflow API details
    $api_key = "YOUR_API_KEY"; // 🔑 Replace with your Roboflow API key
    $workspace_name = "vetcarepredictionapi"; // ✅ Replace with your workspace
    $workflow_id = "custom-workflow-2"; // ⚙️ Replace with your workflow ID

    $imagePath = $_FILES['image']['tmp_name'];

    $url = "https://serverless.roboflow.com/run/$workspace_name/$workflow_id?api_key=$api_key";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'image' => new CURLFile($imagePath)
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(["error" => curl_error($ch)]);
        curl_close($ch);
        exit;
    }

    curl_close($ch);
    echo $response;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VetCareQR Image Prediction</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #c7f5d9, #80c9ff);
      font-family: "Poppins", sans-serif;
      min-height: 100vh;
    }
    .card {
      border: none;
      border-radius: 1rem;
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .preview-img {
      width: 100%;
      max-height: 300px;
      object-fit: contain;
      border-radius: 10px;
      margin-top: 15px;
    }
  </style>
</head>
<body class="d-flex justify-content-center align-items-center">

  <div class="card p-4" style="max-width: 480px;">
    <h3 class="text-center mb-3">🐾 VetCareQR Disease Prediction</h3>

    <form id="uploadForm" enctype="multipart/form-data">
      <div class="mb-3">
        <label for="image" class="form-label">Upload Pet Image:</label>
        <input type="file" class="form-control" name="image" id="image" accept="image/*" required>
      </div>
      <div class="text-center">
        <button type="submit" class="btn btn-primary w-100">Predict</button>
      </div>
    </form>

    <div id="loading" class="text-center mt-3" style="display:none;">
      <div class="spinner-border text-primary" role="status"></div>
      <p>Analyzing image...</p>
    </div>

    <div id="result" class="mt-3 text-center" style="display:none;">
      <img id="preview" class="preview-img" src="#" alt="Preview">
      <h5 class="mt-3 text-success">Prediction Result:</h5>
      <p id="predictionText" class="fw-bold"></p>
    </div>
  </div>

  <script>
    const form = document.getElementById("uploadForm");
    const loading = document.getElementById("loading");
    const result = document.getElementById("result");
    const predictionText = document.getElementById("predictionText");
    const preview = document.getElementById("preview");

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fileInput = document.getElementById("image");
      const file = fileInput.files[0];
      if (!file) return alert("Please select an image.");

      // Show preview and loading
      preview.src = URL.createObjectURL(file);
      result.style.display = "none";
      loading.style.display = "block";

      const formData = new FormData();
      formData.append("image", file);

      try {
        const response = await fetch("", {
          method: "POST",
          body: formData
        });
        const data = await response.json();

        loading.style.display = "none";

        if (data.error) {
          predictionText.textContent = "Error: " + data.error;
          result.style.display = "block";
          return;
        }

        const prediction = data.results?.[0]?.class || "Unknown";
        const confidence = (data.results?.[0]?.confidence * 100 || 0).toFixed(2);

        predictionText.textContent = `${prediction} (${confidence}%)`;
        result.style.display = "block";
      } catch (err) {
        loading.style.display = "none";
        predictionText.textContent = "Error connecting to API.";
        result.style.display = "block";
      }
    });
  </script>

</body>
</html>
