<?php

use App\Http\Controllers\Api\BreedingController;
use App\Http\Controllers\Api\ChickImageController;
use App\Http\Controllers\Api\ComputationResultController;
use App\Http\Controllers\Api\PredictionController;
use App\Http\Controllers\Api\BaseColorController;
use App\Http\Controllers\Api\BirdController;
use App\Http\Controllers\Api\LovebirdSpeciesController;
use App\Http\Controllers\Api\SplitGeneController;
use App\Http\Controllers\Api\VisualMutationController;
use Illuminate\Support\Facades\Route;

Route::get('/lovebird-species', [LovebirdSpeciesController::class, 'index']);
Route::get('/base-colors', [BaseColorController::class, 'index']);
Route::get('/lovebird-species/{species}/base-colors', [BaseColorController::class, 'bySpecies']);
Route::get('/visual-mutations', [VisualMutationController::class, 'index']);
Route::get('/visual-mutations/{visualMutation}', [VisualMutationController::class, 'show']);
Route::get('/lovebird-species/{species}/visual-mutations', [VisualMutationController::class, 'bySpecies']);
Route::get('/split-genes', [SplitGeneController::class, 'index']);
Route::get('/split-genes/{splitGene}', [SplitGeneController::class, 'show']);
Route::get('/lovebird-species/{species}/split-genes', [SplitGeneController::class, 'bySpecies']);

Route::get('/species-breeding-compatibilities', [BreedingController::class, 'compatibilities']);
Route::post('/breeding/validate', [BreedingController::class, 'validatePair']);
Route::post('/breeding/predict', [BreedingController::class, 'predict']);
Route::post('/breeding/analyze', [BreedingController::class, 'analyzePair']);
Route::get('/computation-results/{computationResult}', [ComputationResultController::class, 'show']);
Route::get('/computation-results/{computationResult}/image-prompts.pdf', [ComputationResultController::class, 'downloadImagePrompts']);
Route::post('/outcomes/generate-image', [ChickImageController::class, 'generate']);
Route::post('/computation-results/{computationResult}/generate-all-chick-images', [ChickImageController::class, 'generateAll']);
Route::get('/predictions', [PredictionController::class, 'index']);
Route::get('/predictions/{prediction}', [PredictionController::class, 'show']);

Route::get('/birds', [BirdController::class, 'index']);
Route::post('/birds', [BirdController::class, 'store']);
Route::get('/birds/{bird}', [BirdController::class, 'show']);
Route::put('/birds/{bird}', [BirdController::class, 'update']);
Route::patch('/birds/{bird}', [BirdController::class, 'update']);
Route::delete('/birds/{bird}', [BirdController::class, 'destroy']);
