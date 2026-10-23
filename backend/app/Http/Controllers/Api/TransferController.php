<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveTransferRequest;
use App\Http\Requests\CancelTransferRequest;
use App\Http\Requests\HandoverTransferRequest;
use App\Http\Requests\ReceiveTransferRequest;
use App\Http\Requests\RejectTransferRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Http\Requests\TransferIndexRequest;
use App\Http\Resources\HandoverResource;
use App\Http\Resources\TransferDetailResource;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    public function __construct(private readonly TransferService $transfers) {}

    /**
     * GET /api/transfers
     */
    public function index(TransferIndexRequest $request): JsonResponse
    {
        $query = Transfer::query()
            ->with([
                'asset:id,asset_code,name,qr_token,status',
                'fromLocation:id,code,name',
                'toLocation:id,code,name',
                'requestedBy:id,name',
            ])
            ->when($request->validated('status'), fn ($builder, $value) => $builder->where('status', $value))
            ->when($request->validated('asset_id'), fn ($builder, $value) => $builder->where('asset_id', $value))
            ->when($request->validated('requested_by'), fn ($builder, $value) => $builder->where('requested_by', $value))
            ->when($request->validated('date_from'), fn ($builder, $value) => $builder->whereDate('requested_at', '>=', $value))
            ->when($request->validated('date_to'), fn ($builder, $value) => $builder->whereDate('requested_at', '<=', $value));

        $search = $request->search();

        if ($search !== null) {
            $query->whereHas('asset', fn ($builder) => $builder
                ->where('asset_code', 'like', '%'.$search.'%')
                ->orWhere('name', 'like', '%'.$search.'%'));
        }

        $sortColumn = $request->sortColumn() ?? 'requested_at';
        $sortDirection = $request->sortDirection();

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($request->perPage())
            ->withQueryString();

        return TransferResource::collection($paginator)->additional([
            'message' => 'Transfers retrieved successfully.',
        ])->response();
    }

    /**
     * POST /api/transfers
     */
    public function store(StoreTransferRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $transfer = $this->transfers->request(
            $request->user(),
            (int) $validated['asset_id'],
            (int) $validated['to_location_id'],
            $validated['reason'] ?? null,
            $request,
        );

        return (new TransferDetailResource($transfer->load([
            'asset:id,asset_code,name,qr_token,status',
            'fromLocation:id,code,name',
            'toLocation:id,code,name',
            'requestedBy:id,name',
        ])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/transfers/{transfer}
     */
    public function show(Transfer $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);

        return response()->json([
            'data' => new TransferDetailResource($transfer->load([
                'asset:id,asset_code,name,qr_token,status',
                'fromLocation:id,code,name',
                'toLocation:id,code,name',
                'requestedBy:id,name',
                'approvals.approver:id,name',
                'handover.handedOverBy:id,name',
                'handover.receivedBy:id,name',
            ])),
        ]);
    }

    /**
     * POST /api/transfers/{transfer}/approve
     */
    public function approve(ApproveTransferRequest $request, Transfer $transfer): JsonResponse
    {
        $transfer = $this->transfers->approve(
            $request->user(),
            $transfer,
            $request->validated('notes'),
            $request,
        );

        return response()->json([
            'message' => 'Transfer approved successfully.',
            'data' => new TransferResource($transfer),
        ]);
    }

    /**
     * POST /api/transfers/{transfer}/reject
     */
    public function reject(RejectTransferRequest $request, Transfer $transfer): JsonResponse
    {
        $transfer = $this->transfers->reject(
            $request->user(),
            $transfer,
            (string) $request->validated('notes'),
            $request,
        );

        return response()->json([
            'message' => 'Transfer rejected successfully.',
            'data' => new TransferResource($transfer),
        ]);
    }

    /**
     * POST /api/transfers/{transfer}/handover
     */
    public function handover(HandoverTransferRequest $request, Transfer $transfer): JsonResponse
    {
        $handover = $this->transfers->handover(
            $request->user(),
            $transfer,
            $request->validated('notes'),
            $request,
        );

        return response()->json([
            'message' => 'Handover recorded successfully.',
            'data' => new HandoverResource($handover->load(['handedOverBy:id,name', 'receivedBy:id,name'])),
        ]);
    }

    /**
     * POST /api/transfers/{transfer}/receive
     */
    public function receive(ReceiveTransferRequest $request, Transfer $transfer): JsonResponse
    {
        $transfer = $this->transfers->receive(
            $request->user(),
            $transfer,
            $request->validated('notes'),
            $request,
        );

        return response()->json([
            'message' => 'Transfer received successfully.',
            'data' => new TransferResource($transfer),
        ]);
    }

    /**
     * POST /api/transfers/{transfer}/cancel
     */
    public function cancel(CancelTransferRequest $request, Transfer $transfer): JsonResponse
    {
        $transfer = $this->transfers->cancel(
            $request->user(),
            $transfer,
            $request->validated('reason'),
            $request,
        );

        return response()->json([
            'message' => 'Transfer cancelled successfully.',
            'data' => new TransferResource($transfer),
        ]);
    }
}
