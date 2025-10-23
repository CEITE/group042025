<!-- Pets and Medical Records Section -->
<div class="dashboard-card" id="pets-section">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-paw me-2"></i>Pets & Medical Records</span>
        <div>
            <button class="btn btn-sm btn-outline-primary me-2" id="toggleViewBtn">
                <i class="fas fa-list me-1"></i>View All Medical Records
            </button>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicalRecordModal">
                <i class="fas fa-plus me-1"></i>Add Medical Record
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($pets_with_records)): ?>
            <div class="text-center py-4">
                <i class="fas fa-paw fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Pets Found</h5>
                <p class="text-muted">No pets have been registered in the system yet.</p>
            </div>
        <?php else: ?>
            <!-- Summary View (Default) -->
            <div id="summaryView">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?php echo count($pets_with_records); ?></h3>
                                <p class="mb-0">Total Pets</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center">
                                <h3 class="text-success">
                                    <?php 
                                        $total_records = 0;
                                        foreach ($pets_with_records as $pet) {
                                            $total_records += count($pet['medical_records']);
                                        }
                                        echo $total_records;
                                    ?>
                                </h3>
                                <p class="mb-0">Medical Records</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center">
                                <h3 class="text-warning">
                                    <?php
                                        $completed_records = 0;
                                        foreach ($pets_with_records as $pet) {
                                            foreach ($pet['medical_records'] as $record) {
                                                if ($record['status'] === 'completed') {
                                                    $completed_records++;
                                                }
                                            }
                                        }
                                        echo $completed_records;
                                    ?>
                                </h3>
                                <p class="mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center">
                                <h3 class="text-info">
                                    <?php
                                        $pending_records = 0;
                                        foreach ($pets_with_records as $pet) {
                                            foreach ($pet['medical_records'] as $record) {
                                                if ($record['status'] === 'pending') {
                                                    $pending_records++;
                                                }
                                            }
                                        }
                                        echo $pending_records;
                                    ?>
                                </h3>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h6 class="mb-3">Recent Medical Records</h6>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Pet Name</th>
                                <th>Owner</th>
                                <th>Service Type</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recent_records = [];
                            foreach ($pets_with_records as $pet) {
                                foreach ($pet['medical_records'] as $record) {
                                    $record['pet_name'] = $pet['pet_name'];
                                    $record['owner_name'] = $pet['owner_name'];
                                    $recent_records[] = $record;
                                }
                            }
                            
                            // Sort by date, most recent first
                            usort($recent_records, function($a, $b) {
                                return strtotime($b['service_date']) - strtotime($a['service_date']);
                            });
                            
                            // Show only the 5 most recent records
                            $recent_records = array_slice($recent_records, 0, 5);
                            
                            foreach ($recent_records as $record): 
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['pet_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['owner_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['service_type']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($record['service_date'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($record['status']) {
                                            case 'completed': echo 'success'; break;
                                            case 'under_treatment': echo 'warning'; break;
                                            case 'pending': echo 'secondary'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>"><?php echo ucfirst($record['status']); ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editMedicalRecordModal"
                                            data-record-id="<?php echo $record['record_id']; ?>"
                                            data-service-type="<?php echo htmlspecialchars($record['service_type'] ?? ''); ?>"
                                            data-service-description="<?php echo htmlspecialchars($record['service_description'] ?? ''); ?>"
                                            data-service-date="<?php echo $record['service_date']; ?>"
                                            data-veterinarian="<?php echo htmlspecialchars($record['veterinarian'] ?? ''); ?>"
                                            data-notes="<?php echo htmlspecialchars($record['notes'] ?? ''); ?>"
                                            data-status="<?php echo $record['status']; ?>"
                                            data-clinic-name="<?php echo htmlspecialchars($record['clinic_name'] ?? ''); ?>"
                                            data-clinic-address="<?php echo htmlspecialchars($record['clinic_address'] ?? ''); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <button class="btn btn-sm btn-outline-primary" id="showAllBtn">
                        <i class="fas fa-expand me-1"></i>Show All Records
                    </button>
                </div>
            </div>
            
            <!-- Detailed View (Initially Hidden) -->
            <div id="detailedView" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6>All Medical Records</h6>
                    <button class="btn btn-sm btn-outline-secondary" id="backToSummaryBtn">
                        <i class="fas fa-arrow-left me-1"></i>Back to Summary
                    </button>
                </div>
                
                <div class="accordion" id="petsAccordion">
                    <?php foreach ($pets_with_records as $pet): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#pet<?php echo $pet['pet_id']; ?>">
                                <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                    <div>
                                        <strong><?php echo htmlspecialchars($pet['pet_name']); ?></strong>
                                        <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($pet['species']); ?></span>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($pet['breed']); ?></span>
                                        <span class="badge bg-info">Age: <?php echo htmlspecialchars($pet['age']); ?></span>
                                    </div>
                                    <div>
                                        <small class="text-muted">Owner: <?php echo htmlspecialchars($pet['owner_name']); ?></small>
                                        <span class="badge bg-light text-dark ms-2">
                                            <?php echo count($pet['medical_records']); ?> records
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="pet<?php echo $pet['pet_id']; ?>" class="accordion-collapse collapse" data-bs-parent="#petsAccordion">
                            <div class="accordion-body">
                                <!-- Pet Information -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6>Pet Information</h6>
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($pet['pet_name']); ?></p>
                                        <p><strong>Species:</strong> <?php echo htmlspecialchars($pet['species']); ?></p>
                                        <p><strong>Breed:</strong> <?php echo htmlspecialchars($pet['breed']); ?></p>
                                        <p><strong>Age:</strong> <?php echo htmlspecialchars($pet['age']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Owner Information</h6>
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($pet['owner_name']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($pet['owner_email']); ?></p>
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($pet['owner_phone']); ?></p>
                                    </div>
                                </div>

                                <!-- Medical Records -->
                                <h6>Medical Records</h6>
                                <?php if (empty($pet['medical_records'])): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No medical records found for this pet.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($pet['medical_records'] as $record): ?>
                                    <div class="card medical-record mb-3">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <span class="badge bg-<?php 
                                                        switch($record['status']) {
                                                            case 'completed': echo 'success'; break;
                                                            case 'under_treatment': echo 'warning'; break;
                                                            case 'pending': echo 'secondary'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?>"><?php echo ucfirst($record['status']); ?></span>
                                                </div>
                                                <div>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editMedicalRecordModal"
                                                            data-record-id="<?php echo $record['record_id']; ?>"
                                                            data-service-type="<?php echo htmlspecialchars($record['service_type'] ?? ''); ?>"
                                                            data-service-description="<?php echo htmlspecialchars($record['service_description'] ?? ''); ?>"
                                                            data-service-date="<?php echo $record['service_date']; ?>"
                                                            data-veterinarian="<?php echo htmlspecialchars($record['veterinarian'] ?? ''); ?>"
                                                            data-notes="<?php echo htmlspecialchars($record['notes'] ?? ''); ?>"
                                                            data-status="<?php echo $record['status']; ?>"
                                                            data-clinic-name="<?php echo htmlspecialchars($record['clinic_name'] ?? ''); ?>"
                                                            data-clinic-address="<?php echo htmlspecialchars($record['clinic_address'] ?? ''); ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </div>
                                            </div>
                                            <?php if (!empty($record['service_type'])): ?>
                                                <p><strong>Service Type:</strong> <?php echo htmlspecialchars($record['service_type']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($record['service_description'])): ?>
                                                <p><strong>Description:</strong> <?php echo htmlspecialchars($record['service_description']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($record['veterinarian'])): ?>
                                                <p><strong>Veterinarian:</strong> <?php echo htmlspecialchars($record['veterinarian']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($record['notes'])): ?>
                                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($record['notes']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($record['clinic_name'])): ?>
                                                <p><strong>Clinic:</strong> <?php echo htmlspecialchars($record['clinic_name']); ?></p>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                Service Date: <?php echo date('M d, Y', strtotime($record['service_date'])); ?> | 
                                                Generated: <?php echo date('M d, Y', strtotime($record['generated_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
